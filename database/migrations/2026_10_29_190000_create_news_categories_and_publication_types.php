<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * News categories and publication types become tables the Secretariat
 * maintains under Content > Taxonomies, and sectors and tags gain an order.
 *
 * Until now the two lists were PHP enums (App\Enums\NewsCategory and
 * App\Enums\PublicationType) with their labels in lang/{en,fr,pt}/news.php
 * and publications.php, and the newsroom badge colours were five CSS rules
 * keyed by slug. Adding a category was a deploy.
 *
 * SEEDED WITH EXACTLY TODAY'S VALUES. Same slugs, same labels in the three
 * languages as the lang files carried on 29 October 2026, the same order
 * as the enum cases, and each category's badge in the colour pair its CSS
 * rule drew. The public newsroom and library list the same filters, in the
 * same order, with the same words, before and after
 * (tests/Feature/TaxonomyMigrationTest.php).
 *
 * Sectors keep their English name in the column. Their French and
 * Portuguese names were in lang/{fr,pt}/names.php; they are copied into the
 * translations table so they can be edited, and the lang entries stay as
 * the fallback. Their order is the order the public filters used, which
 * was alphabetical by English name.
 *
 * Items point at a category or type by slug, as they did, with a foreign
 * key: renaming a slug carries every item with it, and deleting a category
 * an item still uses is refused by the database as well as by the screen.
 */
return new class extends Migration
{
    private const CATEGORIES = [
        // slug, colour, en, fr, pt
        ['news', 'navy', 'News', 'Actualité', 'Notícia'],
        ['tender', 'green', 'Tender', 'Appel d\'offres', 'Concurso'],
        ['consultation', 'purple', 'Public consultation', 'Consultation publique', 'Consulta pública'],
        ['communique', 'grey', 'Communique', 'Communiqué', 'Comunicado'],
        ['press_release', 'amber', 'Press release', 'Communiqué de presse', 'Comunicado de imprensa'],
    ];

    private const TYPES = [
        ['annual_report', 'Annual report', 'Rapport annuel', 'Relatório anual'],
        ['guideline', 'Guideline', 'Ligne directrice', 'Diretriz'],
        ['study', 'Study', 'Étude', 'Estudo'],
        ['position_paper', 'Position paper', 'Document de position', 'Documento de posição'],
        ['communique', 'Communique', 'Communiqué', 'Comunicado'],
        ['minutes', 'Minutes', 'Procès-verbal', 'Ata'],
    ];

    /** Public filter order, alphabetical by English name as before. */
    private const SECTOR_ORDER = ['broadcasting' => 1, 'postal' => 2, 'telecommunications' => 3];

    private const SECTOR_NAMES = [
        'telecommunications' => ['fr' => 'Télécommunications', 'pt' => 'Telecomunicações'],
        'broadcasting' => ['fr' => 'Radiodiffusion', 'pt' => 'Radiodifusão'],
        'postal' => ['fr' => 'Services postaux', 'pt' => 'Serviços postais'],
    ];

    public function up(): void
    {
        Schema::create('news_categories', function (Blueprint $table) {
            $table->id();
            // 24, the width of news_items.category it is referenced from.
            $table->string('slug', 24)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            // A key of App\Support\BadgeColours: a background and text
            // token pair measured at 4.5:1, never a free colour value.
            $table->string('colour', 20)->default('navy');
            $table->timestamps();
        });

        Schema::create('publication_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();

        foreach (self::CATEGORIES as $i => [$slug, $colour, $en, $fr, $pt]) {
            $id = DB::table('news_categories')->insertGetId([
                'slug' => $slug, 'sort_order' => $i + 1, 'colour' => $colour,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $this->names('App\\Models\\NewsCategory', $id, ['en' => $en, 'fr' => $fr, 'pt' => $pt]);
        }

        foreach (self::TYPES as $i => [$slug, $en, $fr, $pt]) {
            $id = DB::table('publication_types')->insertGetId([
                'slug' => $slug, 'sort_order' => $i + 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $this->names('App\\Models\\PublicationType', $id, ['en' => $en, 'fr' => $fr, 'pt' => $pt]);
        }

        Schema::table('sectors', function (Blueprint $table) {
            $table->unsignedSmallInteger('sort_order')->default(0)->after('slug');
        });

        foreach (DB::table('sectors')->get(['id', 'slug']) as $sector) {
            DB::table('sectors')->where('id', $sector->id)->update(['sort_order' => self::SECTOR_ORDER[$sector->slug] ?? 10]);

            if (isset(self::SECTOR_NAMES[$sector->slug])) {
                $this->names('App\\Models\\Sector', $sector->id, self::SECTOR_NAMES[$sector->slug]);
            }
        }

        Schema::table('tags', function (Blueprint $table) {
            $table->unsignedSmallInteger('sort_order')->default(0)->after('slug');
        });

        // Any value outside the seeded set would make the foreign key
        // fail; there is none on live, and anything else is news.
        DB::table('news_items')->whereNotIn('category', array_column(self::CATEGORIES, 0))->update(['category' => 'news']);

        Schema::table('news_items', function (Blueprint $table) {
            $table->foreign('category')->references('slug')->on('news_categories')
                ->cascadeOnUpdate()->restrictOnDelete();
        });

        /*
         * publication_type was a database enum. It becomes a string with a
         * foreign key, by the copy and rename the news category took on
         * 10 September 2026: the enum syntax differs between MariaDB and
         * SQLite, and the suite runs on SQLite.
         */
        Schema::table('publications', function (Blueprint $table) {
            $table->string('publication_type_new', 40)->default('guideline')->after('publication_type');
        });
        DB::table('publications')->update(['publication_type_new' => DB::raw('publication_type')]);
        Schema::table('publications', function (Blueprint $table) {
            $table->dropIndex(['publication_type', 'published_on']);
        });
        Schema::table('publications', function (Blueprint $table) {
            $table->dropColumn('publication_type');
        });
        Schema::table('publications', function (Blueprint $table) {
            $table->renameColumn('publication_type_new', 'publication_type');
        });
        Schema::table('publications', function (Blueprint $table) {
            $table->index(['publication_type', 'published_on']);
            $table->foreign('publication_type')->references('slug')->on('publication_types')
                ->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('publications', function (Blueprint $table) {
            $table->dropForeign(['publication_type']);
        });
        Schema::table('publications', function (Blueprint $table) {
            $table->dropIndex(['publication_type', 'published_on']);
        });
        Schema::table('publications', function (Blueprint $table) {
            $table->renameColumn('publication_type', 'publication_type_new');
        });
        Schema::table('publications', function (Blueprint $table) {
            $table->enum('publication_type', array_column(self::TYPES, 0))->default('guideline')->after('slug');
        });
        // A type added on the screen has no place in the enum.
        DB::table('publications')->update(['publication_type' => DB::raw(
            "CASE WHEN publication_type_new IN ('".implode("','", array_column(self::TYPES, 0))."') THEN publication_type_new ELSE 'guideline' END"
        )]);
        Schema::table('publications', function (Blueprint $table) {
            $table->dropColumn('publication_type_new');
        });
        Schema::table('publications', function (Blueprint $table) {
            $table->index(['publication_type', 'published_on']);
        });

        Schema::table('news_items', function (Blueprint $table) {
            $table->dropForeign(['category']);
        });
        DB::table('news_items')->whereNotIn('category', array_column(self::CATEGORIES, 0))->update(['category' => 'news']);

        Schema::table('tags', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
        Schema::table('sectors', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });

        foreach (['App\\Models\\NewsCategory', 'App\\Models\\PublicationType', 'App\\Models\\Sector'] as $type) {
            DB::table('translations')->where('translatable_type', $type)->where('field', 'name')->delete();
        }

        Schema::dropIfExists('publication_types');
        Schema::dropIfExists('news_categories');
    }

    /** @param array<string, string> $names */
    private function names(string $type, int $id, array $names): void
    {
        $now = now();

        foreach ($names as $locale => $value) {
            DB::table('translations')->updateOrInsert(
                ['translatable_type' => $type, 'translatable_id' => $id, 'locale' => $locale, 'field' => 'name'],
                ['value' => $value, 'is_machine_draft' => false, 'created_at' => $now, 'updated_at' => $now],
            );
        }
    }
};

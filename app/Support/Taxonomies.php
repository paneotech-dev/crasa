<?php

namespace App\Support;

use App\Models\NewsCategory;
use App\Models\PublicationType;
use App\Models\Sector;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The four lists kept under Content > Taxonomies, and what each one is.
 *
 * News categories and publication types were enums until 29 October 2026,
 * sectors were a fixed set of three, and tags were edited one slug at a
 * time on the generic data screen. They share one screen because they are
 * the same kind of thing to the Secretariat: a named, ordered list the
 * public filters are built from.
 */
final class Taxonomies
{
    /** kind => model class, the widest slug its column takes, whether it has a colour. */
    public const KINDS = [
        'categories' => ['model' => NewsCategory::class, 'slug_max' => 24, 'colour' => true],
        'publication-types' => ['model' => PublicationType::class, 'slug_max' => 40, 'colour' => false],
        'sectors' => ['model' => Sector::class, 'slug_max' => 40, 'colour' => false],
        'tags' => ['model' => Tag::class, 'slug_max' => 80, 'colour' => false],
    ];

    /** The rows that point at a sector. Every one is counted before a delete. */
    private const SECTOR_REFERENCES = [
        'news_items', 'publications', 'instruments', 'projects', 'committees',
        'indicators', 'announcements', 'authority_sector', 'authority_contact_sector',
    ];

    public static function has(string $kind): bool
    {
        return array_key_exists($kind, self::KINDS);
    }

    /** @return class-string<Model> */
    public static function model(string $kind): string
    {
        return self::KINDS[$kind]['model'];
    }

    public static function hasColour(string $kind): bool
    {
        return self::KINDS[$kind]['colour'];
    }

    /** Every term of a kind, in its public order. */
    public static function list(string $kind)
    {
        $model = self::model($kind);

        return $model::query()
            ->when($kind !== 'sectors', fn ($q) => $q->with('translations'))
            ->ordered()
            ->get();
    }

    /**
     * How many records use this term, deleted ones included: a soft-deleted
     * news item can be restored, and it would come back pointing at nothing.
     */
    public static function usage(string $kind, Model $term): int
    {
        return match ($kind) {
            'categories' => DB::table('news_items')->where('category', $term->slug)->count(),
            'publication-types' => DB::table('publications')->where('publication_type', $term->slug)->count(),
            'tags' => DB::table('taggables')->where('tag_id', $term->getKey())->count(),
            'sectors' => collect(self::SECTOR_REFERENCES)
                ->filter(fn (string $table) => Schema::hasTable($table) && Schema::hasColumn($table, 'sector_id'))
                ->sum(fn (string $table) => DB::table($table)->where('sector_id', $term->getKey())->count()),
        };
    }

    /**
     * The name in each language, as the form shows it.
     *
     * A sector keeps English in its own column and French and Portuguese
     * in the translations table, falling back to lang/{fr,pt}/names.php for
     * a sector created before the names were stored.
     *
     * @return array<string, string>
     */
    public static function names(string $kind, Model $term): array
    {
        if (! $term->exists) {
            return array_fill_keys(Locales::SUPPORTED, '');
        }

        if ($kind === 'sectors') {
            $stored = Sector::storedNames()[$term->getKey()] ?? [];
            $names = [];

            foreach (Locales::SUPPORTED as $locale) {
                $path = 'names.sectors.'.$term->slug;
                $names[$locale] = $locale === Locales::FALLBACK
                    ? (string) $term->rawName()
                    : ($stored[$locale] ?? (trans()->hasForLocale($path, $locale) ? trans($path, [], $locale) : ''));
            }

            return $names;
        }

        $values = $term->translationValuesFor('name');

        return collect(Locales::SUPPORTED)->mapWithKeys(fn ($locale) => [$locale => (string) ($values[$locale] ?? '')])->all();
    }

    /** The name shown in the list, in the interface language. */
    public static function label(string $kind, Model $term): string
    {
        if ($kind === 'sectors') {
            $locale = app()->getLocale();

            return self::names($kind, $term)[$locale] ?: (string) $term->rawName();
        }

        return $term->translated('name', $term->slug);
    }
}

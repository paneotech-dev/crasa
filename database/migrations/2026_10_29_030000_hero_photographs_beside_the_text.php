<?php

use App\Models\MediaAsset;
use App\Models\PageBlock;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Day 2 of the validation workshop: the home hero shows three CRASA
 * photographs from the newsroom, each in a frame beside its text, and its
 * first line is the association's full name.
 *
 * The photographs are found by path, not by id, so the migration means the
 * same thing on every copy of the database. A copy that lacks one of them
 * keeps that slide as it was.
 *
 * Alt text is required in each language before an asset may appear on that
 * language's site (MediaAsset::mayAppearIn), so it is written here. French
 * and Portuguese are flagged as machine drafts for CRASA's review.
 */
return new class extends Migration
{
    private const PHOTOS = [
        'agm' => 'images/legacy/current-news/zec-9064-compressed.jpg',
        'committee' => 'images/legacy/current-news/img-1992.jpg',
        'workshop' => 'images/legacy/crasa-engaged-by-the-upu-to-deliver-capacity-building-workshop-on-postal-sector-regulation-for-the-arab-region/pic-1.jpg',
    ];

    private const ALT = [
        'agm' => [
            'en' => 'Delegates of the CRASA 15th Annual General Meeting in Lilongwe, Malawi, in a group photograph',
            'fr' => "Les délégués de la 15e Assemblée générale annuelle de la CRASA à Lilongwe, au Malawi, en photo de groupe",
            'pt' => 'Os delegados da 15.ª Assembleia Geral Anual da CRASA em Lilongwe, Malawi, numa fotografia de grupo',
        ],
        'committee' => [
            'en' => 'A delegate addresses the CRASA Postal Committee meeting',
            'fr' => 'Un délégué s’adresse à la réunion du Comité postal de la CRASA',
            'pt' => 'Um delegado intervém na reunião do Comité Postal da CRASA',
        ],
        'workshop' => [
            'en' => 'A CRASA facilitator presents at a capacity building workshop on postal sector regulation',
            'fr' => 'Un formateur de la CRASA intervient lors d’un atelier de renforcement des capacités sur la réglementation postale',
            'pt' => 'Um formador da CRASA apresenta num seminário de capacitação sobre regulação do setor postal',
        ],
    ];

    private const CAPTIONS = [
        'caption' => [
            'en' => '15th Annual General Meeting, Lilongwe, Malawi, April 2026',
            'fr' => '15e Assemblée générale annuelle, Lilongwe (Malawi), avril 2026',
            'pt' => '15.ª Assembleia Geral Anual, Lilongwe (Malawi), abril de 2026',
        ],
        'slide_0_caption' => [
            'en' => 'CRASA Postal Committee meeting',
            'fr' => 'Réunion du Comité postal de la CRASA',
            'pt' => 'Reunião do Comité Postal da CRASA',
        ],
        'slide_1_caption' => [
            'en' => 'Capacity building workshop on postal sector regulation',
            'fr' => 'Atelier de renforcement des capacités sur la réglementation postale',
            'pt' => 'Seminário de capacitação sobre regulação do setor postal',
        ],
    ];

    private const EYEBROW = [
        'en' => "Communications Regulators' Association of Southern Africa",
        'fr' => "Association des régulateurs des communications d'Afrique australe",
        'pt' => 'Associação dos Reguladores de Comunicações da África Austral',
    ];

    private const EYEBROW_BEFORE = [
        'en' => 'Communications Regulators of Southern Africa',
        'fr' => "Régulateurs des communications d'Afrique australe",
        'pt' => 'Reguladores das comunicações da África Austral',
    ];

    public function up(): void
    {
        $hero = $this->hero();

        if ($hero === null) {
            return;
        }

        $ids = [];

        foreach (self::PHOTOS as $key => $path) {
            $id = DB::table('media_assets')->where('path', $path)->whereNull('deleted_at')->value('id');

            if ($id === null) {
                continue;
            }

            $ids[$key] = $id;

            foreach (self::ALT[$key] as $locale => $value) {
                $this->put(MediaAsset::class, $id, $locale, 'alt', $value);
            }
        }

        $settings = json_decode($hero->settings, true) ?: [];

        if (isset($ids['agm'])) {
            $settings['image'] = 'media:'.$ids['agm'];
        }

        foreach (['committee' => 0, 'workshop' => 1] as $key => $slide) {
            if (isset($ids[$key], $settings['rotation_items'][$slide])) {
                $settings['rotation_items'][$slide]['image'] = 'media:'.$ids[$key];
            }
        }

        DB::table('page_blocks')->where('id', $hero->id)->update([
            'settings' => json_encode($settings, JSON_UNESCAPED_SLASHES),
            'updated_at' => now(),
        ]);

        foreach (self::EYEBROW as $locale => $value) {
            $this->put(PageBlock::class, $hero->id, $locale, 'eyebrow', $value);
        }

        foreach (self::CAPTIONS as $field => $values) {
            foreach ($values as $locale => $value) {
                $this->put(PageBlock::class, $hero->id, $locale, $field, $value);
            }
        }
    }

    public function down(): void
    {
        $hero = $this->hero();

        if ($hero === null) {
            return;
        }

        $settings = json_decode($hero->settings, true) ?: [];
        $settings['image'] = 'hero';

        foreach (['data', 'institutional-fallback'] as $slide => $image) {
            if (isset($settings['rotation_items'][$slide])) {
                $settings['rotation_items'][$slide]['image'] = $image;
            }
        }

        DB::table('page_blocks')->where('id', $hero->id)->update([
            'settings' => json_encode($settings, JSON_UNESCAPED_SLASHES),
            'updated_at' => now(),
        ]);

        foreach (self::EYEBROW_BEFORE as $locale => $value) {
            $this->put(PageBlock::class, $hero->id, $locale, 'eyebrow', $value);
        }

        DB::table('translations')
            ->where('translatable_type', PageBlock::class)
            ->where('translatable_id', $hero->id)
            ->whereIn('field', array_keys(self::CAPTIONS))
            ->delete();
    }

    private function hero(): ?object
    {
        return DB::table('page_blocks')
            ->join('pages', 'pages.id', '=', 'page_blocks.page_id')
            ->where('pages.slug', 'home')
            ->where('page_blocks.block_type', 'hero')
            ->orderBy('page_blocks.position')
            ->select('page_blocks.id', 'page_blocks.settings')
            ->first();
    }

    private function put(string $type, int $id, string $locale, string $field, string $value): void
    {
        DB::table('translations')->updateOrInsert(
            ['translatable_type' => $type, 'translatable_id' => $id, 'locale' => $locale, 'field' => $field],
            ['value' => $value, 'is_machine_draft' => $locale !== 'en', 'created_at' => now(), 'updated_at' => now()],
        );
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * The About page on one screenful less (29 October 2026).
 *
 * Focus, Vision, Mission and History and the Governance bodies as headed
 * sections in two columns; the Executive Committee and the Secretariat as
 * grids of people read from the "Name, title" lines already in their text;
 * the header's old "Structure" link (#structure) lands on Governance; and a
 * short SADC paragraph after "Who we are", in the footer's own wording,
 * SADC named first.
 *
 * Layout and order only. No stored body text is changed.
 */
return new class extends Migration
{
    private const TYPE = 'App\Models\PageBlock';

    private const LAYOUTS = [
        'vision-mission-history' => ['layout' => 'columns'],
        'governance' => ['layout' => 'columns', 'also_anchor' => 'structure'],
        'executive-committee' => ['layout' => 'people'],
        'secretariat' => ['layout' => 'people'],
    ];

    private const SADC = [
        'en' => [
            'heading' => 'SADC and CRASA',
            'body' => '<p>A Specialised Agency of SADC, established in 1997, harmonising the postal and ICT regulatory environment in the SADC region.</p><p><a href="https://www.sadc.int">Southern African Development Community (SADC)</a></p>',
        ],
        'fr' => [
            'heading' => 'La SADC et la CRASA',
            'body' => '<p>Agence spécialisée de la SADC, créée en 1997, chargée d\'harmoniser l\'environnement réglementaire postal et des TIC dans la région de la SADC.</p><p><a href="https://www.sadc.int">Communauté de développement de l\'Afrique australe (SADC)</a></p>',
        ],
        'pt' => [
            'heading' => 'A SADC e a CRASA',
            'body' => '<p>Agência Especializada da SADC, criada em 1997, para harmonizar o ambiente regulatório postal e das TIC na região da SADC.</p><p><a href="https://www.sadc.int">Comunidade de Desenvolvimento da África Austral (SADC)</a></p>',
        ],
    ];

    public function up(): void
    {
        $page = DB::table('pages')->where('slug', 'about')->value('id');

        if ($page === null) {
            return;
        }

        foreach (self::LAYOUTS as $anchor => $layout) {
            $block = DB::table('page_blocks')->where('page_id', $page)->where('anchor', $anchor)->first();

            if ($block === null) {
                continue;
            }

            $settings = json_decode((string) $block->settings, true) ?: [];
            DB::table('page_blocks')->where('id', $block->id)->update([
                'settings' => json_encode(array_merge($settings, $layout)),
                'updated_at' => now(),
            ]);
        }

        if (DB::table('page_blocks')->where('page_id', $page)->where('anchor', 'sadc')->exists()) {
            return;
        }

        $after = DB::table('page_blocks')->where('page_id', $page)->where('anchor', 'who-we-are')->value('position');
        $position = $after === null ? 0 : $after + 1;

        DB::table('page_blocks')->where('page_id', $page)->where('position', '>=', $position)->increment('position');

        $id = DB::table('page_blocks')->insertGetId([
            'page_id' => $page,
            'block_type' => 'rich_text',
            'position' => $position,
            'anchor' => 'sadc',
            'is_visible' => true,
            'settings' => json_encode(['alignment' => 'start']),
            'is_sample' => false,
            'needs_content' => false,
            'imported_from_legacy' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (self::SADC as $locale => $fields) {
            foreach ($fields as $field => $value) {
                DB::table('translations')->insert([
                    'translatable_type' => self::TYPE,
                    'translatable_id' => $id,
                    'locale' => $locale,
                    'field' => $field,
                    'value' => $value,
                    'is_machine_draft' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $page = DB::table('pages')->where('slug', 'about')->value('id');

        if ($page === null) {
            return;
        }

        foreach (array_keys(self::LAYOUTS) as $anchor) {
            $block = DB::table('page_blocks')->where('page_id', $page)->where('anchor', $anchor)->first();

            if ($block !== null) {
                $settings = json_decode((string) $block->settings, true) ?: [];
                unset($settings['layout'], $settings['also_anchor']);
                DB::table('page_blocks')->where('id', $block->id)->update(['settings' => json_encode($settings)]);
            }
        }

        $sadc = DB::table('page_blocks')->where('page_id', $page)->where('anchor', 'sadc')->first();

        if ($sadc !== null) {
            DB::table('translations')->where('translatable_type', self::TYPE)->where('translatable_id', $sadc->id)->delete();
            DB::table('page_blocks')->where('id', $sadc->id)->delete();
            DB::table('page_blocks')->where('page_id', $page)->where('position', '>', $sadc->position)->decrement('position');
        }
    }
};

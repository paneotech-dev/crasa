<?php

use App\Models\PageBlock;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * The home hero headline fits two lines in its column; "across Southern
 * Africa" moves into the paragraph, so the full sentence is still read.
 * Only text still as it was written tonight is changed.
 */
return new class extends Migration
{
    private const TEXT = [
        'headline' => [
            'en' => ['Harmonising ICT and postal regulation across Southern Africa.', 'Harmonising ICT and postal regulation'],
            'fr' => ['Harmoniser la réglementation des TIC et des services postaux en Afrique australe.', 'Harmoniser la régulation des TIC et de la poste'],
            'pt' => ['Harmonizar a regulamentação das TIC e dos serviços postais na África Austral.', 'Harmonizar a regulação das TIC e dos correios'],
        ],
        'subhead' => [
            'en' => ['CRASA brings together', 'Across Southern Africa, CRASA brings together'],
            'fr' => ['La CRASA réunit', 'En Afrique australe, la CRASA réunit'],
            'pt' => ['A CRASA reúne', 'Na África Austral, a CRASA reúne'],
        ],
    ];

    public function up(): void
    {
        $this->swap(0, 1);
    }

    public function down(): void
    {
        $this->swap(1, 0);
    }

    private function swap(int $from, int $to): void
    {
        $hero = DB::table('page_blocks')->join('pages', 'pages.id', '=', 'page_blocks.page_id')
            ->where('pages.slug', 'home')->where('page_blocks.block_type', 'hero')->value('page_blocks.id');

        if ($hero === null) {
            return;
        }

        foreach (self::TEXT as $field => $locales) {
            foreach ($locales as $locale => $pair) {
                $row = DB::table('translations')->where([
                    'translatable_type' => PageBlock::class, 'translatable_id' => $hero, 'field' => $field, 'locale' => $locale,
                ])->first();

                if ($row === null) {
                    continue;
                }

                // The headline is replaced whole; the paragraph only at its start.
                $value = $field === 'headline'
                    ? ($row->value === $pair[$from] ? $pair[$to] : null)
                    : (str_starts_with($row->value, $pair[$from]) && ! str_starts_with($row->value, $pair[$to]) || ($from === 1 && str_starts_with($row->value, $pair[$from]))
                        ? $pair[$to].substr($row->value, strlen($pair[$from]))
                        : null);

                if ($value !== null) {
                    DB::table('translations')->where('id', $row->id)->update(['value' => $value, 'updated_at' => now()]);
                }
            }
        }
    }
};

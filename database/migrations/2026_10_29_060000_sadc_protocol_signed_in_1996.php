<?php

use App\Models\PageBlock;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * The SADC Protocol on Transport, Communications and Meteorology was signed
 * in 1996. The About text imported from the old site gave 1995; the history
 * timeline already says 1996. Only the protocol's year in brackets changes.
 */
return new class extends Migration
{
    private const PAIRS = [
        'en' => ['Meteorology (1995)', 'Meteorology (1996)'],
        'fr' => ['la météorologie (1995)', 'la météorologie (1996)'],
        'pt' => ['Meteorologia (1995)', 'Meteorologia (1996)'],
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
        foreach (self::PAIRS as $locale => $pair) {
            DB::table('translations')
                ->where('translatable_type', PageBlock::class)
                ->where('locale', $locale)
                ->where('value', 'like', '%'.$pair[$from].'%')
                ->orderBy('id')
                ->each(fn ($row) => DB::table('translations')->where('id', $row->id)->update([
                    'value' => str_replace($pair[$from], $pair[$to], $row->value),
                    'updated_at' => now(),
                ]));
        }
    }
};

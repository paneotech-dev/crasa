<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * The members page standfirst said three member regulators work in French.
 * CRASA's count is two (meeting of 28 October 2026). Only the number word
 * changes, in each language, and only where the old wording is still there,
 * so an editor's own rewrite is left alone and running this twice is a
 * no-op.
 */
return new class extends Migration
{
    /** @var array<string, array{0: string, 1: string}> */
    private const SWAPS = [
        'en' => ['Four work in Portuguese, three in French', 'Four work in Portuguese, two in French'],
        'fr' => ['Quatre travaillent en portugais, trois en français', 'Quatre travaillent en portugais, deux en français'],
        'pt' => ['Quatro trabalham em português, três em francês', 'Quatro trabalham em português, dois em francês'],
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
        $pageId = DB::table('pages')->where('slug', 'members')->value('id');

        if ($pageId === null) {
            return;
        }

        foreach (self::SWAPS as $locale => $pair) {
            $rows = DB::table('translations')
                ->where('translatable_type', 'App\\Models\\Page')
                ->where('translatable_id', $pageId)
                ->where('locale', $locale)
                ->where('field', 'standfirst')
                ->where('value', 'like', '%'.$pair[$from].'%')
                ->get(['id', 'value']);

            foreach ($rows as $row) {
                DB::table('translations')->where('id', $row->id)->update([
                    'value' => str_replace($pair[$from], $pair[$to], $row->value),
                    'updated_at' => now(),
                ]);
            }
        }
    }
};

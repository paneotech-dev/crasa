<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * The members page standfirst, corrected with the client: two members work
 * in Portuguese (Angola, Mozambique), two in French (DR Congo, Comoros), and
 * Mauritius in both English and French. Only the clause is replaced, so any
 * other edit to the sentence is kept.
 */
return new class extends Migration
{
    private const CLAUSES = [
        'en' => ['Four work in Portuguese, two in French, and all fourteen', 'Two work in Portuguese, two in French, and Mauritius in both English and French; all fourteen'],
        'fr' => ['Quatre travaillent en portugais, deux en français, et les quatorze', 'Deux travaillent en portugais, deux en français, et Maurice en anglais et en français ; les quatorze'],
        'pt' => ['Quatro trabalham em português, dois em francês, e as catorze', 'Dois trabalham em português, dois em francês, e a Maurícia em inglês e em francês; as catorze'],
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
        $page = DB::table('pages')->where('slug', 'members')->value('id');

        if ($page === null) {
            return;
        }

        foreach (self::CLAUSES as $locale => $pair) {
            $row = DB::table('translations')->where([
                'translatable_type' => Page::class, 'translatable_id' => $page, 'locale' => $locale, 'field' => 'standfirst',
            ])->first();

            if ($row && str_contains($row->value, $pair[$from])) {
                DB::table('translations')->where('id', $row->id)->update([
                    'value' => str_replace($pair[$from], $pair[$to], $row->value),
                    'updated_at' => now(),
                ]);
            }
        }
    }
};

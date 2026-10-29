<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Content that still read in English on the French or Portuguese site: a
 * translation row that is missing or holds the English text unchanged.
 *
 * Each draft is matched on the record type, the field and the English text,
 * not on an id, so it only lands where the English is what was translated.
 * A row is written only when the French or Portuguese is missing or equals
 * the English byte for byte; a translation someone has already written is
 * never touched. Every row written here is marked is_machine_draft, so the
 * translation lists show it as a draft for the Secretariat to review.
 * Running it twice changes nothing the second time.
 */
return new class extends Migration
{
    /** @var array<int, array{0: string, 1: string, 2: string, 3: string, 4: string}> type, field, locale, English, draft */
    private const DRAFTS = [
        ['App\\Models\\Publication', 'title', 'fr', 'SADC Roaming Guidelines, 2019 revision', 'Lignes directrices de la SADC sur l\'itinérance, révision de 2019'],
        ['App\\Models\\Publication', 'title', 'fr', 'SADC Roaming Guidelines, 2015', 'Lignes directrices de la SADC sur l\'itinérance, 2015'],
        ['App\\Models\\Publication', 'title', 'fr', 'SADC Roaming Guidelines, 2024 revision', 'Lignes directrices de la SADC sur l\'itinérance, révision de 2024'],
        ['App\\Models\\Tag', 'name', 'pt', 'Roaming', 'Itinerância'],
    ];

    public function up(): void
    {
        foreach (self::DRAFTS as [$type, $field, $locale, $english, $draft]) {
            $sources = DB::table('translations')
                ->where('translatable_type', $type)
                ->where('field', $field)
                ->where('locale', 'en')
                ->where('value', $english)
                ->get(['translatable_id', 'value'])
                // MariaDB compares without accents or case; this does not.
                ->filter(fn ($row) => $row->value === $english);

            foreach ($sources as $source) {
                $key = ['translatable_type' => $type, 'translatable_id' => $source->translatable_id, 'locale' => $locale, 'field' => $field];
                $current = DB::table('translations')->where($key)->first(['id', 'value']);

                if ($current === null) {
                    DB::table('translations')->insert($key + ['value' => $draft, 'is_machine_draft' => true, 'created_at' => now(), 'updated_at' => now()]);
                } elseif ($current->value === null || $current->value === '' || $current->value === $english) {
                    DB::table('translations')->where('id', $current->id)->update(['value' => $draft, 'is_machine_draft' => true, 'updated_at' => now()]);
                }
            }
        }
    }

    public function down(): void
    {
        // Only the drafts still as written here go back to the English; a
        // draft the Secretariat has since reviewed or edited stays.
        foreach (self::DRAFTS as [$type, $field, $locale, $english, $draft]) {
            $ids = DB::table('translations')
                ->where('translatable_type', $type)
                ->where('field', $field)
                ->where('locale', $locale)
                ->where('is_machine_draft', true)
                ->where('value', $draft)
                ->get(['id', 'value'])
                ->filter(fn ($row) => $row->value === $draft)
                ->pluck('id');

            DB::table('translations')->whereIn('id', $ids)->update(['value' => $english, 'is_machine_draft' => false, 'updated_at' => now()]);
        }
    }
};

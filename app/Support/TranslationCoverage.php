<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * How complete a record's translations are, per language.
 *
 * Screen 05 puts this at the top of the editor as a bar and a sentence:
 * "5 of 5 approved" in green, "2 approved, 2 machine drafts, 1
 * untranslated" in amber. The sentence is the part that matters. A single
 * percentage would let a language that is 100 percent machine-drafted read
 * as finished.
 */
final class TranslationCoverage
{
    public const APPROVED = 'approved';

    public const MACHINE = 'machine';

    public const UNTRANSLATED = 'untranslated';

    /**
     * @param  Model&object{translationValuesFor: callable}  $record
     * @param  list<string>  $fields
     * @return array<string, array{approved: int, machine: int, untranslated: int, total: int, ready: bool}>
     */
    public static function for(Model $record, array $fields): array
    {
        $coverage = [];

        foreach (Locales::SUPPORTED as $locale) {
            $counts = [self::APPROVED => 0, self::MACHINE => 0, self::UNTRANSLATED => 0];

            foreach ($fields as $field) {
                $counts[self::state($record, $field, $locale)]++;
            }

            $coverage[$locale] = $counts + [
                'total' => count($fields),
                // Ready means every field is translated and none of them is
                // a machine draft. Machine drafts are never published as
                // approved copy for this client.
                'ready' => $counts[self::APPROVED] === count($fields),
            ];
        }

        return $coverage;
    }

    /** approved, machine or untranslated, for one field in one language. */
    public static function state(Model $record, string $field, string $locale): string
    {
        $translation = $record->translations
            ->firstWhere(fn ($t) => $t->locale === $locale && $t->field === $field);

        if ($translation === null || trim((string) $translation->value) === '') {
            return self::UNTRANSLATED;
        }

        return $translation->is_machine_draft ? self::MACHINE : self::APPROVED;
    }

    /**
     * The sentence beside the bar. Written out rather than reduced to a
     * percentage, for the reason above.
     *
     * @param  array{approved: int, machine: int, untranslated: int, total: int}  $counts
     */
    public static function describe(array $counts): string
    {
        if ($counts['approved'] === $counts['total']) {
            return __('admin.coverage_all', ['n' => $counts['total']]);
        }

        $parts = [__('admin.coverage_approved', ['n' => $counts['approved']])];

        if ($counts['machine'] > 0) {
            $parts[] = __('admin.coverage_machine', ['n' => $counts['machine']]);
        }

        if ($counts['untranslated'] > 0) {
            $parts[] = __('admin.coverage_untranslated', ['n' => $counts['untranslated']]);
        }

        return implode(', ', $parts);
    }

    /** The share of fields that are approved, for the bar. */
    public static function percentage(array $counts): int
    {
        return $counts['total'] === 0 ? 0 : (int) round($counts['approved'] / $counts['total'] * 100);
    }
}

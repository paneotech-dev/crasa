<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * The resolution rule from docs/DATA_MODEL.md, section 5.
 *
 * Request locale, then English, then nothing: the field is hidden and the
 * gap is logged. An empty heading is never rendered, and a record is never
 * hidden because one of its fields is untranslated.
 */
final class TranslationResolver
{
    /**
     * @param  array<string, string|null>  $values  Text keyed by locale.
     * @param  array<string, mixed>  $context  Written to the log when nothing resolves.
     */
    public static function resolve(array $values, ?string $locale = null, array $context = []): ?ResolvedText
    {
        $requested = $locale ?? app()->getLocale();

        foreach ([$requested, Locales::FALLBACK] as $candidate) {
            $value = $values[$candidate] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return new ResolvedText($value, $candidate, $requested);
            }
        }

        $present = array_keys(array_filter(
            $values,
            fn ($value) => is_string($value) && trim($value) !== ''
        ));

        // A field written in no language at all is an optional field nobody
        // filled in (a hero with no separate heading, a figure with no
        // source): normal, and only worth a debug line. Until 14 October
        // 2026 every one was a warning, about 190 a day, which buried the
        // case that matters: text that exists in another language but not
        // in the reader's or in English.
        if ($present === []) {
            Log::debug('Translatable field is empty in every language. The field is hidden.', $context + [
                'requested_locale' => $requested,
            ]);

            return null;
        }

        Log::warning('Translatable field has no text in the requested language or in English. The field is hidden.', $context + [
            'requested_locale' => $requested,
            'locales_present' => $present,
        ]);

        return null;
    }
}

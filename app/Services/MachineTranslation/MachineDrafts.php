<?php

namespace App\Services\MachineTranslation;

use App\Models\AuditLog;
use App\Models\Concerns\HasTranslations;
use App\Models\Translation;
use App\Support\Locales;
use Illuminate\Database\Eloquent\Model;

/**
 * Item 1b: when an item is approved in one language, the other two are
 * drafted by machine at once. Each draft row is flagged is_machine_draft,
 * so the public page shows the "translated automatically" note and the
 * translations screen lists it for review.
 *
 * A row a person wrote (is_machine_draft false) is never touched. A row that
 * is still a machine draft is drafted again, because the approved source
 * text may have changed since.
 */
final class MachineDrafts
{
    /** The configured driver. Tests bind their own Translator in the container. */
    public static function translator(): Translator
    {
        if (app()->bound(Translator::class)) {
            return app(Translator::class);
        }

        return match (config('machine_translation.driver')) {
            'deepl' => new DeeplTranslator(
                config('machine_translation.deepl.key'),
                config('machine_translation.deepl.url'),
                (int) config('machine_translation.deepl.timeout', 20),
            ),
            'rafiki' => new RafikiTranslator(
                config('machine_translation.rafiki.key'),
                config('machine_translation.rafiki.url'),
                config('machine_translation.rafiki.model'),
                (int) config('machine_translation.rafiki.timeout', 30),
            ),
            default => new NoTranslator,
        };
    }

    public static function configured(): bool
    {
        return self::translator()->available();
    }

    /**
     * The .env settings still empty for the selected driver, named in the
     * back-office notice. With no driver selected, the driver setting itself.
     *
     * @return list<string>
     */
    public static function missingSettings(): array
    {
        $required = match (config('machine_translation.driver')) {
            'deepl' => ['DEEPL_API_KEY' => 'machine_translation.deepl.key'],
            'rafiki' => ['RAFIKI_API_KEY' => 'machine_translation.rafiki.key'],
            default => null,
        };

        if ($required === null) {
            return ['CRASA_MT_DRIVER'];
        }

        return array_keys(array_filter($required, function (string $path) {
            $value = config($path);

            return ! is_string($value) || trim($value) === '';
        }));
    }

    /**
     * Draft the missing languages of one record.
     *
     * @return array<string, list<string>> locale => fields drafted
     */
    public static function draft(Model $record, ?int $by = null): array
    {
        if (! in_array(HasTranslations::class, class_uses_recursive($record), true)) {
            return [];
        }

        $fields = $record->translatableFields();
        $rows = $record->translations()->get();
        $source = self::sourceLocale($rows, $fields);

        if ($source === null) {
            return [];
        }

        $translator = self::translator();

        if (! $translator->available()) {
            AuditLog::record('machine_translation_skipped', $record, ['driver' => $translator->name(), 'source' => $source], $by);

            return [];
        }

        $drafted = [];

        foreach (Locales::SUPPORTED as $target) {
            if ($target === $source) {
                continue;
            }

            foreach ($fields as $field) {
                $text = self::value($rows, $source, $field);
                $existing = $rows->first(fn (Translation $t) => $t->locale === $target && $t->field === $field);

                if ($text === null || ($existing !== null && ! $existing->is_machine_draft && trim((string) $existing->value) !== '')) {
                    continue;
                }

                $value = $translator->translate($text, $source, $target, $text !== strip_tags($text));

                if ($value === null) {
                    continue;
                }

                Translation::updateOrCreate(
                    [
                        'translatable_type' => $record->getMorphClass(),
                        'translatable_id' => $record->getKey(),
                        'locale' => $target,
                        'field' => $field,
                    ],
                    ['value' => $value, 'translated_by' => null, 'is_machine_draft' => true],
                );

                $drafted[$target][] = $field;
            }
        }

        $record->unsetRelation('translations');

        if ($drafted !== []) {
            $details = ['driver' => $translator->name(), 'source' => $source, 'drafted' => $drafted];

            if ($translator instanceof ReportsModel && $translator->models() !== []) {
                $details['model'] = implode(', ', $translator->models());
                $details += array_filter(['fallback' => $translator->fallback()]);
            }

            AuditLog::record('machine_translation_drafted', $record, $details, $by);
        }

        return $drafted;
    }

    /**
     * The language the item was approved in: English when English carries
     * text, otherwise the language with most fields written by a person.
     *
     * @param  \Illuminate\Support\Collection<int, Translation>  $rows
     * @param  list<string>  $fields
     */
    private static function sourceLocale($rows, array $fields): ?string
    {
        $best = null;
        $bestCount = 0;

        foreach (Locales::SUPPORTED as $locale) {
            $count = $rows->filter(fn (Translation $t) => $t->locale === $locale && ! $t->is_machine_draft
                && in_array($t->field, $fields, true) && trim((string) $t->value) !== '')->count();

            if ($locale === Locales::FALLBACK && $count > 0) {
                return $locale;
            }

            if ($count > $bestCount) {
                [$best, $bestCount] = [$locale, $count];
            }
        }

        return $best;
    }

    /** @param  \Illuminate\Support\Collection<int, Translation>  $rows */
    private static function value($rows, string $locale, string $field): ?string
    {
        $row = $rows->first(fn (Translation $t) => $t->locale === $locale && $t->field === $field && ! $t->is_machine_draft);
        $value = $row?->value;

        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}

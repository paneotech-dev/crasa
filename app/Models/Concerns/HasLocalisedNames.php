<?php

namespace App\Models\Concerns;

use App\Models\Translation;
use App\Support\Locales;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * French and Portuguese for names kept in one database column.
 *
 * Countries, sectors, committees and indicators keep their name in a
 * single English column, so until 14 October 2026 a French or Portuguese
 * page said "South Africa" and "Telecommunications". The translations live
 * in lang/{fr,pt}/names.php, keyed by the record's ISO code or slug, and
 * this returns them in place of the column.
 *
 * NEVER IN THE BACK OFFICE. An edit form there reads the same attribute:
 * given the French name it would save it over the English one. So the
 * administration always sees the column. Commands and seeders run in
 * English, so they see it too; a queued mail in the reader's language gets
 * the translated name, which is the point.
 *
 * A model using this defines localisedNames(): the lang group, the key
 * attribute and the localised fields.
 *
 * WRITTEN IN THE BACK OFFICE (Day 3). A member state or an indicator added
 * after the lang files were written has no line in them, so the record's
 * form takes a French and a Portuguese value as well. Those are kept in the
 * translations table under the column's name and win over the lang file;
 * an empty one falls back to the lang file, then to the column. They are
 * read for every model in one query per request, not one per record.
 */
trait HasLocalisedNames
{
    private const STORED_NAMES = 'crasa.localised-names';

    /** @return array{group: string, key: ?string, fields: list<string>} */
    abstract protected function localisedNames(): array;

    /** The stored values go with the record, as translations do. */
    public static function bootHasLocalisedNames(): void
    {
        static::deleted(function ($model) {
            if (in_array(SoftDeletes::class, class_uses_recursive($model), true) && ! $model->isForceDeleting()) {
                return;
            }

            Translation::query()
                ->where('translatable_type', $model->getMorphClass())
                ->where('translatable_id', $model->getKey())
                ->whereIn('field', $model->localisedFields())
                ->delete();

            app()->forgetInstance(self::STORED_NAMES);
        });
    }

    /** @return list<string> */
    public function localisedFields(): array
    {
        return $this->localisedNames()['fields'];
    }

    /** The French or Portuguese value written in the back office, if any. */
    public function storedLocalisedName(string $field, string $locale): ?string
    {
        if (! $this->exists) {
            return null;
        }

        return self::storedNames()[$this->getMorphClass()][$this->getKey()][$field][$locale] ?? null;
    }

    /** The value the lang files give, if any. Shown beside the form field. */
    public function fileLocalisedName(string $field, string $locale): ?string
    {
        $spec = $this->localisedNames();
        $id = $spec['key'] !== null ? parent::getAttributeValue($spec['key']) : null;
        if (! is_string($id) || $id === '') {
            return null;
        }

        $path = $spec['fields'] === ['name'] ? "names.{$spec['group']}.{$id}" : "names.{$spec['group']}.{$id}.{$field}";

        return trans()->hasForLocale($path, $locale) ? (string) trans($path, [], $locale) : null;
    }

    /**
     * Write or clear one stored value. Empty, or the same as the lang
     * file, clears it: a copy of the file's line would stop following it.
     */
    public function setLocalisedName(string $field, string $locale, ?string $value, ?int $by = null): void
    {
        $value = $value === null ? '' : trim($value);
        $where = [
            'translatable_type' => $this->getMorphClass(),
            'translatable_id' => $this->getKey(),
            'locale' => $locale,
            'field' => $field,
        ];

        if ($value === '' || $value === $this->fileLocalisedName($field, $locale)) {
            Translation::where($where)->delete();
        } else {
            Translation::updateOrCreate($where, ['value' => $value, 'translated_by' => $by, 'is_machine_draft' => false]);
        }

        app()->forgetInstance(self::STORED_NAMES);
    }

    /** @return array<string, array<int, array<string, array<string, string>>>> */
    private static function storedNames(): array
    {
        $app = app();
        if ($app->bound(self::STORED_NAMES)) {
            return $app->make(self::STORED_NAMES);
        }

        $map = [];
        try {
            Translation::query()
                ->whereIn('translatable_type', [
                    \App\Models\Country::class, \App\Models\Authority::class,
                    \App\Models\Committee::class, \App\Models\Indicator::class,
                ])
                ->whereIn('field', ['name', 'definition'])
                ->where('locale', '!=', Locales::FALLBACK)
                ->get(['translatable_type', 'translatable_id', 'field', 'locale', 'value'])
                ->each(function (Translation $t) use (&$map) {
                    $map[$t->translatable_type][$t->translatable_id][$t->field][$t->locale] = $t->value;
                });
        } catch (\Throwable) {
            // A fresh install mid-migration: the lang files still answer.
        }

        $app->instance(self::STORED_NAMES, $map);

        return $map;
    }

    public function getAttributeValue($key)
    {
        $value = parent::getAttributeValue($key);

        $spec = $this->localisedNames();
        if (! in_array($key, $spec['fields'], true) || ! is_string($value)) {
            return $value;
        }

        return $this->localisedName($key, $value, $spec);
    }

    /** The column itself, whatever the language. */
    public function rawName(string $field = 'name'): ?string
    {
        return parent::getAttributeValue($field);
    }

    private function localisedName(string $field, string $value, array $spec): string
    {
        $locale = app()->getLocale();

        if ($locale === Locales::FALLBACK) {
            return $value;
        }

        $request = app()->bound('request') ? request() : null;
        if ($request?->route()?->getName() !== null && str_starts_with((string) $request->route()->getName(), 'admin.')) {
            return $value;
        }

        $stored = $this->storedLocalisedName($field, $locale);
        if ($stored !== null && trim($stored) !== '') {
            return $stored;
        }

        // trans() falls back to English and then to the key itself; neither
        // is a translation, so only a line the locale has counts.
        return $this->fileLocalisedName($field, $locale) ?? $value;
    }
}

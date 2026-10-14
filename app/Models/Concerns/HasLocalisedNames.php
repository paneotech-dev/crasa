<?php

namespace App\Models\Concerns;

use App\Support\Locales;

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
 */
trait HasLocalisedNames
{
    /** @return array{group: string, key: string, fields: list<string>} */
    abstract protected function localisedNames(): array;

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

        $id = parent::getAttributeValue($spec['key']);
        if (! is_string($id) || $id === '') {
            return $value;
        }

        $path = $spec['fields'] === ['name'] ? "names.{$spec['group']}.{$id}" : "names.{$spec['group']}.{$id}.{$field}";
        $line = trans($path, [], $locale);

        // trans() falls back to English and then to the key itself; neither is a translation.
        return is_string($line) && $line !== $path && trans()->hasForLocale($path, $locale) ? $line : $value;
    }
}

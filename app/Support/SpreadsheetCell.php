<?php

namespace App\Support;

/**
 * A value that leaves the platform in a CSV file.
 *
 * A spreadsheet opening a CSV reads a cell that starts with =, +, -, @, a
 * tab or a carriage return as a formula. Delegate names, roles and notes
 * are typed by whoever fills the public form, so a filing could carry a
 * formula into the Secretariat's spreadsheet. A leading apostrophe makes
 * such a cell plain text. Every CSV of delegate registrations writes its
 * rows through here.
 *
 * The Excel exports (SimpleXlsx) store strings as inline text, which a
 * spreadsheet never evaluates, so they do not need it.
 */
final class SpreadsheetCell
{
    private const TRIGGERS = ['=', '+', '-', '@', "\t", "\r"];

    public static function safe(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return in_array($value[0], self::TRIGGERS, true) ? "'".$value : $value;
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array<int, mixed>
     */
    public static function row(array $row): array
    {
        return array_map(self::safe(...), $row);
    }
}

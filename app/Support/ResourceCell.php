<?php

namespace App\Support;

use App\Admin\Resource;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

/**
 * One cell of a resource table.
 *
 * Kept out of the Blade template because the interesting cases are not
 * presentation: a relation column resolves through the relation rather
 * than printing an id, a status column renders as the platform's status
 * pill so the colours keep their fixed meanings, and a date is formatted
 * once rather than in eleven templates.
 */
final class ResourceCell
{
    public static function render(Resource $definition, Model $record, string $column): string|HtmlString
    {
        // A translatable field comes from the translations table, with the
        // English fallback and its notice already applied.
        if (in_array($column, $definition->translatable, true) && method_exists($record, 'translate')) {
            return $record->translate($column)?->value ?? '';
        }

        // A relation column, drawn as the related record's own name rather
        // than as a foreign key nobody can read.
        if ($record->relationLoaded($column) || method_exists($record, $column)) {
            $related = $record->getAttribute($column);

            if ($related instanceof Model) {
                return (string) ($related->getAttribute('name')
                    ?? $related->getAttribute('title')
                    ?? $related->getAttribute('slug')
                    ?? '#'.$related->getKey());
            }
        }

        $value = $record->getAttribute($column);

        if ($value === null) {
            return '';
        }

        if ($value instanceof Carbon) {
            return $value->isoFormat('D MMM YYYY');
        }

        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        if (is_bool($value)) {
            return __($value ? 'admin.yes' : 'admin.no');
        }

        // Status columns keep the fixed meanings from CLAUDE.md: green for
        // validated and in force, amber for draft and pending, red for
        // repealed and rejected, slate for archived and superseded.
        if (in_array($column, ['status', 'state'], true)) {
            return new HtmlString(
                '<span class="a-pill" data-status="'.e((string) $value).'">'.e(__('admin.status_'.$value) === 'admin.status_'.$value ? (string) $value : __('admin.status_'.$value)).'</span>'
            );
        }

        return (string) $value;
    }
}

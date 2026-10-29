<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * The state of a record's translation in each language, for the admin lists
 * (TR-03, light): missing, machine translated (the existing is_machine_draft
 * flag) or reviewed. A word and an icon for each, never colour alone. No new
 * workflow: it reads what is already stored.
 */
final class TranslationState
{
    public const MISSING = 'missing';

    public const MACHINE = 'machine';

    public const REVIEWED = 'reviewed';

    /** @return array<string, string> locale => state */
    public static function of(Model $record, string $field = 'title'): array
    {
        $translations = $record->relationLoaded('translations') ? $record->translations : $record->translations()->get();
        $states = [];

        foreach (Locales::SUPPORTED as $locale) {
            $row = $translations->first(fn ($t) => $t->field === $field && $t->locale === $locale && trim((string) $t->value) !== '');
            $states[$locale] = $row === null ? self::MISSING : ($row->is_machine_draft ? self::MACHINE : self::REVIEWED);
        }

        return $states;
    }

    public static function cell(Model $record, string $field = 'title'): HtmlString
    {
        $icons = [
            self::REVIEWED => '<path d="M20 6 9 17l-5-5"/>',
            self::MACHINE => '<circle cx="12" cy="12" r="8" stroke-dasharray="3 3"/>',
            self::MISSING => '<path d="M18 6 6 18M6 6l12 12"/>',
        ];

        $items = '';
        foreach (self::of($record, $field) as $locale => $state) {
            $label = __('office.tr_state_'.$state);
            $items .= '<li class="a-trstate" data-state="'.$state.'">'
                .'<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$icons[$state].'</svg>'
                .'<span class="a-trstate__lang">'.e(strtoupper($locale)).'</span> '
                .'<span class="a-trstate__word">'.e($label).'</span></li>';
        }

        return new HtmlString('<ul class="a-trstates" role="list">'.$items.'</ul>');
    }
}

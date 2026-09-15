<?php

namespace App\Support;

/**
 * Content that came across from the old site and says nothing.
 *
 * Four of the forty-three meetings imported from the events host carry the
 * WordPress theme's demo Latin as their agenda -- "Alienum phaedrum
 * torquatos nec eu, vis detraxit periculis ex" -- and all four are public
 * meetings with genuine titles and genuine dates. Removing the shortcodes
 * around it would leave lorem ipsum on a treaty body's events page.
 *
 * WHY THIS DETECTS RATHER THAN LISTS RECORD IDS. A list of ids has to be
 * maintained: somebody supplies a real agenda, the id stays in the list,
 * and the real agenda is hidden behind a placeholder nobody can explain.
 * Matching the filler itself means the placeholder disappears the moment
 * the filler does, with nothing to remember to remove.
 *
 * The phrases below are the opening of the demo passage shipped with the
 * theme. They are Latin, they are specific, and no CRASA agenda contains
 * them. This is deliberately narrow: it recognises one known filler text,
 * not "text that looks like filler".
 *
 * NOTHING IS DELETED. The stored value is untouched, so the Secretariat can
 * still read what came across and CRASA can replace it.
 */
class PlaceholderContent
{
    /** @var list<string> */
    public const FILLER = [
        'Alienum phaedrum',
        'torquatos nec eu',
        'vis detraxit periculis',
        'Mei an pericula euripidis',
        'facilisis urbanitas moderatius',
    ];

    public static function isFiller(?string $value): bool
    {
        $value = (string) $value;

        if (trim($value) === '') {
            return false;
        }

        foreach (self::FILLER as $phrase) {
            if (stripos($value, $phrase) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * What a reader sees instead.
     *
     * Deliberately not the bracketed form the legal pages use for content
     * CRASA still owes: those are notes to the Secretariat inside a
     * published document, and this is a statement to the public that a
     * meeting's agenda is not settled. Confusing the two would make one of
     * them meaningless.
     */
    public static function agendaNotice(): string
    {
        return __('events.agenda_to_be_confirmed');
    }
}

<?php

namespace App\Support;

/**
 * Text as a reader sees it, without the notes written for CRASA (15 October 2026).
 *
 * The drafted legal pages carry bracketed notes for CRASA and its counsel
 * ("[CRASA to confirm the data controller of record ...]"). They stay in the
 * stored text, where ClientReview reads the outstanding list from them, so
 * filling a blank in still removes it from that list. On the owner's
 * instruction they are not shown to readers: this strips them at render
 * time, and a section left with nothing but its heading is left out.
 */
final class PublishedText
{
    private const NOTE = '/[ \t]*\**\[(?:CRASA|La CRASA|A CRASA)\b[^\]]*\]\**/u';

    /** Sentences that described the old on-screen sample notice. */
    public const SAMPLE_SENTENCES = [
        ' A figure marked as demonstration data has not been validated by the member state and is shown to demonstrate the platform; that notice stays on screen and in every export until the national regulator confirms the value.',
        " Une valeur signalée comme donnée de démonstration n'a pas été validée par l'État membre ; l'avis reste affiché à l'écran et dans chaque export jusqu'à confirmation par le régulateur national.",
        ' Um valor assinalado como dado de demonstração não foi validado pelo Estado membro; o aviso permanece no ecrã e em cada exportação até confirmação pelo regulador nacional.',
    ];

    /** @return array{text: string, notes: list<string>} */
    public static function withoutDraftingNotes(string $text): array
    {
        $text = str_replace(self::SAMPLE_SENTENCES, '', $text);

        $notes = [];
        $paragraphs = [];
        foreach (preg_split("/\n{2,}/", $text) as $paragraph) {
            if (preg_match_all(self::NOTE, $paragraph, $found)) {
                foreach ($found[0] as $note) {
                    $notes[] = trim($note, " \t*[]");
                }
                $paragraph = trim((string) preg_replace(self::NOTE, '', $paragraph));
                // Only a heading left ("Your rights.", "Where it is held. On servers."): leave the section out.
                if (str_word_count(strip_tags($paragraph)) <= 6) {
                    continue;
                }
            }
            $paragraphs[] = $paragraph;
        }

        return ['text' => implode("\n\n", $paragraphs), 'notes' => $notes];
    }
}

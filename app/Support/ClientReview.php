<?php

namespace App\Support;

use App\Models\Page;
use App\Models\PageBlock;

/**
 * What is still outstanding on a page the client has not signed off.
 *
 * The privacy notice and the terms of use carry a complete draft with named
 * blanks in it: governing law, retention periods, the data protection
 * contact. Each blank is written as a square-bracketed sentence naming who
 * must supply it. Until 9 September 2026 the page announced its own draft
 * status in its opening paragraph, which put the project's internal state
 * in front of every reader of a public legal page.
 *
 * The list is derived rather than maintained. A second copy of "what is
 * outstanding", kept by hand alongside the text, is a copy that goes stale
 * the first time somebody fills a blank in and does not remember to update
 * it. Reading the brackets out of the text cannot go stale: fill the blank
 * in, and the item leaves the list.
 *
 * NOTHING HERE IS PUBLIC. No public template calls this class.
 */
class ClientReview
{
    /**
     * A bracketed blank is a sentence, not a citation. The lower bound
     * keeps ordinary editorial brackets -- "[sic]", a bracketed acronym --
     * out of the list; the upper bound stops a stray unmatched bracket
     * swallowing a paragraph.
     */
    private const PATTERN = '/\[([^\[\]]{20,600})\]/u';

    /**
     * Every outstanding item on a page, read from the English text.
     *
     * English only, deliberately. The same blank is written three times,
     * once per language, and a list that repeats each item three times is a
     * list nobody reads. English is the language the drafts were written in
     * and the one the resolution rule falls back to.
     *
     * @return list<string>
     */
    public static function outstanding(Page $page): array
    {
        $items = [];

        foreach ($page->blocks as $block) {
            foreach (self::fromBlock($block) as $item) {
                $items[] = $item;
            }
        }

        return array_values(array_unique($items));
    }

    /** @return list<string> */
    private static function fromBlock(PageBlock $block): array
    {
        $items = [];

        foreach ($block->translatableFields() as $field) {
            $text = $block->translationValuesFor($field)[Locales::FALLBACK] ?? null;

            if (! is_string($text) || $text === '') {
                continue;
            }

            preg_match_all(self::PATTERN, $text, $matches);

            foreach ($matches[1] as $match) {
                // The drafts emphasise some blanks with Markdown bold. The
                // list wants the sentence, not the markup.
                $items[] = trim(str_replace('**', '', $match));
            }
        }

        return $items;
    }

    /** Whether the back office should still be showing a review flag. */
    public static function isOutstanding(Page $page): bool
    {
        return $page->needs_client_review && $page->client_reviewed_at === null;
    }
}

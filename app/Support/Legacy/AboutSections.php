<?php

namespace App\Support\Legacy;

/**
 * The legacy About page, section by section.
 *
 * The page is one Webflow document with five anchored sections, and the
 * new About page has blocks standing on a "being prepared" note for three
 * of them. The legacy site states all three, in CRASA's own words, and
 * those words are better than a placeholder and better than anything this
 * project could write on the Secretariat's behalf.
 *
 * PLAIN TEXT, NOT HTML. The rich_text block renders its body through
 * nl2br(e(...)), so a block body is escaped text with blank lines between
 * paragraphs. Handing it markup would print the tags on the page.
 */
class AboutSections
{
    /** The anchors, in the order the legacy page carries them. */
    public const ANCHORS = [
        'vision-mission-statement',
        'structure',
        'secretariat',
        'executive-committee',
        'members',
    ];

    /**
     * @return array<string, array{heading: string, body: string}>
     */
    public static function parse(string $html): array
    {
        $body = PageParser::section($html);
        $bounds = self::bounds($body);
        $sections = [];

        foreach ($bounds as $anchor => [$start, $end]) {
            $slice = substr($body, $start, $end - $start);

            $sections[$anchor] = [
                'heading' => self::heading($slice),
                'body' => match ($anchor) {
                    'secretariat', 'executive-committee' => self::people($slice),
                    'vision-mission-statement' => self::tabs($slice),
                    default => self::prose($slice),
                },
            ];
        }

        return $sections;
    }

    /** @return array<string, array{int, int}> */
    private static function bounds(string $body): array
    {
        $found = [];

        foreach (self::ANCHORS as $anchor) {
            $at = strpos($body, 'id="'.$anchor.'"');

            if ($at !== false) {
                $found[$anchor] = $at;
            }
        }

        $bounds = [];
        $offsets = array_values($found);

        foreach (array_keys($found) as $index => $anchor) {
            $bounds[$anchor] = [$offsets[$index], $offsets[$index + 1] ?? strlen($body)];
        }

        return $bounds;
    }

    private static function heading(string $slice): string
    {
        if (preg_match('/<h3[^>]*>(.*?)<\/h3>/is', $slice, $m)) {
            return PageParser::text($m[1]);
        }

        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $slice, $m)) {
            return PageParser::text($m[1]);
        }

        return '';
    }

    /**
     * The intro paragraph and whatever follows it.
     *
     * Webflow writes the whole of a long section into one div separated by
     * pairs of <br>, with <strong> standing in for subheadings. Those breaks
     * are the paragraph structure and are the only structure there is.
     */
    private static function prose(string $slice): string
    {
        if (! preg_match('/<div class="text-large getting-started-intro-text"[^>]*>(.*?)<\/div>/is', $slice, $m)) {
            return '';
        }

        return self::paragraphs($m[1]);
    }

    /**
     * The four tabs: focus, vision, mission, history.
     *
     * A tabbed panel is a control, not content. Flattened to four labelled
     * paragraphs, because a reader who wants CRASA's mission should not have
     * to find and press a tab to see it, and because screen 03 draws no tab
     * control on the About page.
     */
    private static function tabs(string $slice): string
    {
        preg_match_all(
            '/<h4[^>]*>(.*?)<\/h4>\s*<div class="text-large"[^>]*>(.*?)<\/div>/is',
            $slice,
            $matches,
            PREG_SET_ORDER
        );

        $parts = [];

        foreach ($matches as $match) {
            $heading = PageParser::text($match[1]);
            $text = self::paragraphs($match[2]);

            if ($heading !== '' && $text !== '') {
                $parts[] = $heading."\n".$text;
            }
        }

        return implode("\n\n", $parts);
    }

    /**
     * An intro paragraph followed by a name and a role per person.
     *
     * Names are left exactly as the legacy site writes them. A person's own
     * name is not a heading to be re-cased.
     */
    private static function people(string $slice): string
    {
        $parts = [];

        if (preg_match('/<div class="text-large getting-started-intro-text"[^>]*>(.*?)<\/div>/is', $slice, $m)) {
            $parts[] = self::paragraphs($m[1]);
        }

        preg_match_all(
            '/<h5 class="team-member-heading">(.*?)<\/h5>\s*<div[^>]*>(.*?)<\/div>/is',
            $slice,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $name = PageParser::text($match[1]);
            $role = PageParser::text($match[2]);

            if ($name !== '') {
                $parts[] = $role === '' ? $name : $name.', '.$role;
            }
        }

        return implode("\n\n", array_filter($parts));
    }

    /** Webflow's paired line breaks are paragraph boundaries. */
    private static function paragraphs(string $html): string
    {
        $html = preg_replace('/(<br\s*\/?>\s*){2,}/i', "\n\n", $html) ?? $html;
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;

        /*
         * The legacy page sets its subheadings in <strong> capitals:
         * ANNUAL GENERAL MEETING, EXECUTIVE COMMITTEE. Sentence case is
         * CLAUDE.md's first writing rule and it has exactly two carve-outs,
         * neither of which is a subheading in a page block.
         */
        $lines = array_map(
            fn (string $line) => TitleCase::apply(PageParser::text($line)),
            explode("\n", $html)
        );

        $text = implode("\n", $lines);
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }
}

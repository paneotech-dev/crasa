<?php

namespace App\Support;

/**
 * A plain-text block body read as sections and people.
 *
 * The Secretariat types block bodies as plain text with blank lines between
 * paragraphs. Two About sections carry more structure than that shows:
 * Focus, Vision, Mission and History are four headed sections, and the
 * Executive Committee and the Secretariat end in one line per person,
 * "Name, title". Reading that structure from the text, rather than adding
 * fields for it, keeps the back office exactly as it is: an editor adds a
 * person by adding a line.
 *
 *   A heading    a line of at most 60 characters with no closing
 *                punctuation, alone in its paragraph or first in it
 *   A person     a paragraph of one line, "Name, title", no full stop
 *   Text         everything else
 */
final class StructuredText
{
    private const HEADING_MAX = 60;

    private const PERSON_MAX = 160;

    /**
     * @return list<array{type: 'heading'|'text'|'person', text?: string, name?: string, title?: string}>
     */
    public static function parse(?string $body): array
    {
        $body = str_replace(["\r\n", "\r"], "\n", trim((string) $body));

        if ($body === '' || preg_match('/<[a-zA-Z\/]/', $body)) {
            return [];
        }

        $items = [];

        foreach (preg_split('/\n\s*\n/', $body) as $paragraph) {
            $lines = array_values(array_filter(array_map('trim', explode("\n", $paragraph)), fn ($l) => $l !== ''));

            if ($lines === []) {
                continue;
            }

            if (count($lines) === 1 && ($person = self::person($lines[0])) !== null) {
                $items[] = ['type' => 'person'] + $person;

                continue;
            }

            if (self::isHeading($lines[0]) && (count($lines) === 1 || ! self::isHeading($lines[1]))) {
                $items[] = ['type' => 'heading', 'text' => array_shift($lines)];
            }

            if ($lines !== []) {
                $items[] = ['type' => 'text', 'text' => implode("\n", $lines)];
            }
        }

        return $items;
    }

    /**
     * The body as an introduction and headed sections.
     *
     * @return array{intro: list<string>, sections: list<array{heading: string, paragraphs: list<string>}>}
     */
    public static function sections(?string $body): array
    {
        $intro = [];
        $sections = [];

        foreach (self::parse($body) as $item) {
            if ($item['type'] === 'heading') {
                $sections[] = ['heading' => $item['text'], 'paragraphs' => []];
            } else {
                $text = $item['type'] === 'person' ? $item['name'].', '.$item['title'] : $item['text'];

                if ($sections === []) {
                    $intro[] = $text;
                } else {
                    $sections[array_key_last($sections)]['paragraphs'][] = $text;
                }
            }
        }

        return ['intro' => $intro, 'sections' => $sections];
    }

    /**
     * The body as text paragraphs and people.
     *
     * @return array{intro: list<string>, people: list<array{name: string, title: string}>}
     */
    public static function people(?string $body): array
    {
        $intro = [];
        $people = [];

        foreach (self::parse($body) as $item) {
            if ($item['type'] === 'person') {
                $people[] = ['name' => $item['name'], 'title' => $item['title']];
            } else {
                $intro[] = $item['text'];
            }
        }

        return ['intro' => $intro, 'people' => $people];
    }

    private static function isHeading(string $line): bool
    {
        return mb_strlen($line) <= self::HEADING_MAX
            && ! preg_match('/[.:;,!?]$/u', $line)
            && ! str_contains($line, ', ');
    }

    /** @return array{name: string, title: string}|null */
    private static function person(string $line): ?array
    {
        if (mb_strlen($line) > self::PERSON_MAX || preg_match('/[.:;!?]$/u', $line)) {
            return null;
        }

        if (! preg_match('/^([^,]{3,70}),\s*(.{2,90})$/u', $line, $m)) {
            return null;
        }

        return ['name' => trim($m[1]), 'title' => trim($m[2])];
    }
}

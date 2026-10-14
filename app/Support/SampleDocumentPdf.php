<?php

namespace App\Support;

/**
 * Multi-page sample PDFs for the demonstration folders (14 October 2026).
 *
 * The folders shown to the regulators were empty, and a one-line
 * placeholder is not something to open in front of a room. Each sample here
 * is a real text PDF: a title page, then content pages, every page under a
 * CRASA header and above a footer that says "Sample document". The text is
 * real text, so it can be selected, searched and thumbnailed.
 *
 * Written by hand, as PublicationSeeder's placeholder is, so the platform
 * gains no dependency: Helvetica, WinAnsi encoding, a cross-reference table.
 */
final class SampleDocumentPdf
{
    private const W = 595;   // A4 in points

    private const H = 842;

    /**
     * @param  list<array{heading: string, paragraphs: list<string>}>  $sections
     */
    public static function make(string $title, string $subtitle, string $date, array $sections): string
    {
        $pages = [self::titlePage($title, $subtitle, $date)];
        $lines = [];
        foreach ($sections as $section) {
            $lines[] = ['h', $section['heading']];
            foreach ($section['paragraphs'] as $paragraph) {
                foreach (self::wrap($paragraph, 92) as $line) {
                    $lines[] = ['p', $line];
                }
                $lines[] = ['gap', ''];
            }
        }

        // Content pages: as many lines as fit between header and footer.
        $perPage = 44;
        $chunk = [];
        foreach ($lines as $line) {
            $chunk[] = $line;
            if (count($chunk) >= $perPage) {
                $pages[] = $chunk;
                $chunk = [];
            }
        }
        if ($chunk !== []) {
            $pages[] = $chunk;
        }

        $total = count($pages);
        $streams = [];
        foreach ($pages as $i => $page) {
            $streams[] = self::frame($i + 1, $total, $title).(is_string($page) ? $page : self::body($page));
        }

        return self::assemble($streams);
    }

    /** A one-line placeholder written by an earlier seeder ("Demonstration document: ..."). */
    public static function isPlaceholder(?string $bytes): bool
    {
        return $bytes === null || $bytes === '' || (strlen($bytes) < 2048 && str_contains($bytes, 'Demonstration document:'));
    }

    /** A sample for a demonstration publication, from its title, type and year. */
    public static function forPublication(string $title, string $type, int $year): string
    {
        $kind = str_replace('_', ' ', $type);

        return self::make($title, "CRASA {$kind}, {$year}", "June {$year}", [
            ['heading' => '1. Background', 'paragraphs' => [
                "This {$kind} sets out the position agreed among the member regulators of CRASA on the subject of its title. It was prepared by the Secretariat with the relevant committee, consulted on with member regulators, and adopted in {$year}.",
                'Member regulators retain full authority over their national markets. Regional instruments of this kind are not binding in themselves; they take effect as each regulator adopts them into national regulation, licence conditions or administrative practice, with the adaptations its national law requires.',
            ]],
            ['heading' => '2. Scope', 'paragraphs' => [
                'The instrument applies to licensed operators and service providers in the sectors concerned, and to the regulators that supervise them. It covers the obligations operators may be asked to meet, the information regulators should collect to monitor them, and the cooperation expected between regulators where services cross borders.',
                'It does not address matters reserved to governments, such as national policy objectives, taxation or the allocation of public funds, except where the regulator administers them under its own statute.',
            ]],
            ['heading' => '3. Principles', 'paragraphs' => [
                'Transparency: obligations, prices and conditions of service should be published in a form users can understand and compare. Non-discrimination: operators in comparable positions should be treated alike. Proportionality: obligations should be no heavier than needed to achieve their purpose, and reviewed as markets change.',
                'Regional consistency: where member regulators impose obligations on the same subject, they should use common definitions and reporting periods, so that results can be compared across the region and operators active in several states face a coherent framework.',
            ]],
            ['heading' => '4. Recommended measures', 'paragraphs' => [
                'Regulators should publish the conditions that apply under this instrument, monitor compliance through regular returns from operators, and report the results to CRASA through the annual regulatory return, so that progress across the region can be followed on the platform.',
                'Where disputes arise between operators in different member states, the regulators concerned should consult each other before taking decisions, and may ask the Secretariat to facilitate. Decisions should be shared with the other member regulators once taken.',
                'Regulators should consult operators and consumer representatives before adopting national measures under this instrument, and allow a reasonable period for implementation.',
            ]],
            ['heading' => '5. Implementation and review', 'paragraphs' => [
                'Each member regulator is invited to report, within twelve months of adoption, the national measures it has taken under this instrument and any difficulties encountered. The Secretariat will compile these reports for the relevant committee.',
                'The instrument will be reviewed after three years, or earlier if market or technological developments make a review necessary. Proposed amendments are prepared by the relevant committee and adopted by the Annual General Meeting.',
            ]],
        ]);
    }

    private static function titlePage(string $title, string $subtitle, string $date): string
    {
        $s = self::text(60, 560, 'F2', 26, 'CRASA');
        $s .= self::text(60, 535, 'F1', 11, "Communications Regulators' Association of Southern Africa");
        $s .= "0.06 0.11 0.18 RG 2 w 60 515 m 535 515 l S\n";
        $y = 470;
        foreach (self::wrap($title, 40) as $line) {
            $s .= self::text(60, $y, 'F2', 22, $line);
            $y -= 30;
        }
        $y -= 6;
        foreach (self::wrap($subtitle, 70) as $line) {
            $s .= self::text(60, $y, 'F1', 13, $line);
            $y -= 18;
        }
        $s .= self::text(60, $y - 20, 'F1', 11, $date);
        $s .= self::text(60, 150, 'F2', 12, 'SAMPLE DOCUMENT');
        $s .= self::text(60, 132, 'F1', 10, 'Prepared for the demonstration of the CRASA platform. It is not an official CRASA document,');
        $s .= self::text(60, 118, 'F1', 10, 'and none of its figures or findings should be quoted.');

        return $s;
    }

    /** Header and footer, on every page. */
    private static function frame(int $n, int $total, string $title): string
    {
        $s = self::text(60, 800, 'F2', 9, 'CRASA');
        $s .= self::text(100, 800, 'F1', 9, self::clip($title, 70));
        $s .= "0.75 0.75 0.75 RG 0.5 w 60 792 m 535 792 l S\n";
        $s .= "0.75 0.75 0.75 RG 0.5 w 60 52 m 535 52 l S\n";
        $s .= self::text(60, 38, 'F2', 9, 'Sample document');
        $s .= self::text(150, 38, 'F1', 9, 'Not an official CRASA publication. Removed by content:strip-samples.');
        $s .= self::text(495, 38, 'F1', 9, "Page {$n} of {$total}");

        return $s;
    }

    /** @param  list<array{0: string, 1: string}>  $lines */
    private static function body(array $lines): string
    {
        $s = '';
        $y = 760;
        foreach ($lines as [$kind, $text]) {
            if ($kind === 'h') {
                $y -= 8;
                $s .= self::text(60, $y, 'F2', 13, $text);
                $y -= 22;
            } elseif ($kind === 'gap') {
                $y -= 7;
            } else {
                $s .= self::text(60, $y, 'F1', 10.5, $text);
                $y -= 14.5;
            }
        }

        return $s;
    }

    private static function text(float $x, float $y, string $font, float $size, string $text): string
    {
        return "BT /{$font} {$size} Tf {$x} {$y} Td (".self::escape($text).") Tj ET\n";
    }

    private static function escape(string $text): string
    {
        $text = (string) iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private static function clip(string $text, int $max): string
    {
        return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 1).'…' : $text;
    }

    /** @return list<string> */
    private static function wrap(string $text, int $width): array
    {
        return explode("\n", wordwrap($text, $width, "\n", true));
    }

    /** @param  list<string>  $streams */
    private static function assemble(array $streams): string
    {
        $objects = [];
        $objects[1] = '<</Type/Catalog/Pages 2 0 R>>';
        $kids = [];
        $next = 5;
        $pageObjects = [];
        foreach ($streams as $stream) {
            $pageObjects[] = [$next, $next + 1, $stream];
            $kids[] = $next.' 0 R';
            $next += 2;
        }
        $objects[2] = '<</Type/Pages/Kids['.implode(' ', $kids).']/Count '.count($streams).'>>';
        $objects[3] = '<</Type/Font/Subtype/Type1/BaseFont/Helvetica/Encoding/WinAnsiEncoding>>';
        $objects[4] = '<</Type/Font/Subtype/Type1/BaseFont/Helvetica-Bold/Encoding/WinAnsiEncoding>>';
        foreach ($pageObjects as [$page, $content, $stream]) {
            $objects[$page] = '<</Type/Page/Parent 2 0 R/MediaBox[0 0 '.self::W.' '.self::H.']/Contents '.$content.' 0 R/Resources<</Font<</F1 3 0 R/F2 4 0 R>>>>>>';
            $objects[$content] = '<</Length '.strlen($stream).">>stream\n".$stream.'endstream';
        }
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$object}\nendobj\n";
        }
        $xref = strlen($pdf);
        $count = max(array_keys($objects)) + 1;
        $pdf .= "xref\n0 {$count}\n0000000000 65535 f \n";
        for ($i = 1; $i < $count; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<</Size {$count}/Root 1 0 R>>\nstartxref\n{$xref}\n%%EOF\n";

        return $pdf;
    }
}

<?php

namespace App\Support;

/**
 * A comparison as a text PDF with the chart as an image (Day 3, item C).
 *
 * The chart is the server-drawn SVG rasterised to JPEG by Imagick; the
 * title, the table, the sources and the date are real text, so the file
 * can be searched and read aloud. Written by hand in the manner of
 * SampleDocumentPdf: Helvetica, WinAnsi encoding, a cross-reference table.
 * No browser is involved, so the result is the same on every browser.
 */
final class ComparisonPdf
{
    private const W = 595;   // A4 in points

    private const H = 842;

    private const MARGIN = 48;

    /**
     * @param  list<array{0: string, 1: string, 2: string, 3: string}>  $rows  member state, value, period, source
     * @param  list<string>  $headers
     * @param  list<string>  $footer  source lines, notes, date and permalink
     */
    public static function make(string $title, string $subtitle, string $jpeg, int $imageWidth, int $imageHeight, array $headers, array $rows, array $footer): string
    {
        $pages = [];

        // Page one: the title and the chart, scaled to the page width.
        $boxW = self::W - 2 * self::MARGIN;
        $boxH = self::H - 2 * self::MARGIN - 70;
        $scale = min($boxW / max(1, $imageWidth), $boxH / max(1, $imageHeight));
        $drawW = round($imageWidth * $scale, 2);
        $drawH = round($imageHeight * $scale, 2);
        $top = self::H - self::MARGIN;

        $ops = self::text(self::MARGIN, $top - 16, 'F2', 16, $title)
            .self::text(self::MARGIN, $top - 34, 'F1', 10, $subtitle)
            .'q '.$drawW.' 0 0 '.$drawH.' '.self::MARGIN.' '.round($top - 50 - $drawH, 2)." cm /Im1 Do Q\n";
        $pages[] = $ops;

        // Following pages: the table as text, then the footer lines.
        $lines = [['h', $headers]];
        foreach ($rows as $row) {
            $lines[] = ['r', $row];
        }
        $lines[] = ['gap', []];
        foreach ($footer as $line) {
            foreach (self::wrap($line, 100) as $part) {
                $lines[] = ['p', [$part]];
            }
        }

        $columns = [self::MARGIN, 230, 330, 400];
        $y = $top - 16;
        $ops = self::text(self::MARGIN, $y, 'F2', 13, $title);
        $y -= 26;
        foreach ($lines as [$kind, $cells]) {
            if ($y < self::MARGIN + 20) {
                $pages[] = $ops;
                $ops = '';
                $y = $top - 16;
            }
            if ($kind === 'gap') {
                $y -= 10;

                continue;
            }
            if ($kind === 'p') {
                $ops .= self::text(self::MARGIN, $y, 'F1', 9, $cells[0]);
                $y -= 13;

                continue;
            }
            foreach (array_values($cells) as $i => $cell) {
                $limit = $i === 3 ? 34 : 30;
                $ops .= self::text($columns[$i] ?? self::MARGIN, $y, $kind === 'h' ? 'F2' : 'F1', 9, mb_strimwidth($cell, 0, $limit, '...'));
            }
            $y -= 15;
        }
        $pages[] = $ops;

        return self::assemble($pages, $jpeg, $imageWidth, $imageHeight, $title);
    }

    private static function text(float $x, float $y, string $font, int $size, string $text): string
    {
        return 'BT /'.$font.' '.$size.' Tf '.$x.' '.$y.' Td ('.self::escape($text).") Tj ET\n";
    }

    private static function escape(string $text): string
    {
        $encoded = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
        if ($encoded === false) {
            $encoded = preg_replace('/[^\x20-\x7E]/', '?', $text) ?? '';
        }

        return strtr($encoded, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => ' ', "\n" => ' ']);
    }

    /** @return list<string> */
    private static function wrap(string $text, int $width): array
    {
        return explode("\n", wordwrap($text, $width, "\n", true));
    }

    /** @param list<string> $pages */
    private static function assemble(array $pages, string $jpeg, int $imageWidth, int $imageHeight, string $title): string
    {
        // 1 catalog, 2 pages, 3 Helvetica, 4 Helvetica-Bold, 5 image,
        // 6 info, then a page and its content stream per page.
        $objects = [];
        $kids = [];
        foreach ($pages as $i => $ops) {
            $kids[] = (7 + 2 * $i).' 0 R';
        }
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($pages).' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $objects[5] = '<< /Type /XObject /Subtype /Image /Width '.$imageWidth.' /Height '.$imageHeight
            .' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($jpeg)." >>\nstream\n".$jpeg."\nendstream";
        $objects[6] = '<< /Title ('.self::escape($title).') /Producer (CRASA) >>';
        foreach ($pages as $i => $ops) {
            $page = 7 + 2 * $i;
            $objects[$page] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.self::W.' '.self::H.'] '
                .'/Resources << /Font << /F1 3 0 R /F2 4 0 R >> /XObject << /Im1 5 0 R >> >> /Contents '.($page + 1).' 0 R >>';
            $objects[$page + 1] = '<< /Length '.strlen($ops)." >>\nstream\n".$ops."endstream";
        }
        ksort($objects);

        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($out);
            $out .= $number." 0 obj\n".$body."\nendobj\n";
        }
        $xref = strlen($out);
        $out .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $out .= sprintf("%010d 00000 n \n", $offset);
        }
        $out .= 'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R /Info 6 0 R >>\nstartxref\n".$xref."\n%%EOF\n";

        return $out;
    }
}

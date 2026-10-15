<?php

namespace App\Services;

use App\Models\Report;
use Illuminate\Support\Str;
use Imagick;
use RuntimeException;

/**
 * The brief as a document: a cover page and a data page.
 *
 * BUILD_SPEC 5.5: "This is the artefact that ends up in ministerial
 * briefings." So it opens on a cover with the mark, the title and the
 * date, and the figures start on page two. A one-page table with a logo at
 * the top is a printout; a cover page is what makes it a document, and the
 * difference is visible on a table in Lilongwe.
 *
 * Drawn as SVG and converted by Imagick with Ghostscript, both already on
 * this host. No headless browser, no new dependency.
 */
final class ReportDocument
{
    private const WIDTH = 1240;

    private const HEIGHT = 1754;   // A4 at 150dpi, portrait

    public function __construct(private readonly ReportBuilder $builder) {}

    public function pdf(Report $report): string
    {
        if (! extension_loaded('imagick')) {
            throw new RuntimeException('Imagick is required to export a report.');
        }

        $pages = new Imagick;

        foreach ([$this->cover($report), $this->body($report)] as $svg) {
            $page = new Imagick;
            $page->setBackgroundColor('white');
            $page->readImageBlob($svg);
            $page->setImageFormat('pdf');
            $pages->addImage($page);
        }

        $pages->resetIterator();
        $pages->setImageFormat('pdf');

        $blob = $pages->getImagesBlob();
        $pages->clear();

        return $blob;
    }

    public function filename(Report $report): string
    {
        return 'crasa-'.Str::slug($report->subject_key.'-'.($report->period ?: 'latest')).'.pdf';
    }

    /** Page one: the mark, the title, the date, the permalink. */
    public function cover(Report $report): string
    {
        $compiled = $this->builder->compile($report);
        $e = fn (?string $s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_XML1, 'UTF-8');

        $parts = ['<rect width="'.self::WIDTH.'" height="'.self::HEIGHT.'" fill="#FFFFFF"/>'];

        // A navy band across the top third, the mark on it. Institutional,
        // and it survives being photocopied.
        $parts[] = '<rect x="0" y="0" width="'.self::WIDTH.'" height="420" fill="#0F1B2D"/>';
        $parts[] = $this->mark(96, 96, 64);
        $parts[] = '<text x="180" y="140" font-family="Georgia,serif" font-size="34" font-weight="600" fill="#FFFFFF">'.$e(__('site.acronym')).'</text>';
        $parts[] = '<text x="180" y="176" font-family="Helvetica,Arial,sans-serif" font-size="16" fill="#E5E0D5">'.$e(__('site.full_name')).'</text>';

        $parts[] = '<text x="96" y="300" font-family="Helvetica,Arial,sans-serif" font-size="15" fill="#7FC79E">'
            .$e(__('reports.cover_kicker')).'</text>';

        foreach ($this->wrap($compiled['title'], 34) as $i => $line) {
            $parts[] = '<text x="96" y="'.(352 + $i * 46).'" font-family="Georgia,serif" font-size="40" font-weight="600" fill="#FFFFFF">'.$e($line).'</text>';
        }

        $parts[] = '<text x="96" y="500" font-family="Helvetica,Arial,sans-serif" font-size="19" fill="#1F2937">'.$e($compiled['subtitle']).'</text>';

        $parts[] = '<text x="96" y="'.(self::HEIGHT - 220).'" font-family="Helvetica,Arial,sans-serif" font-size="15" fill="#5C6B7E">'
            .$e(__('reports.cover_generated', ['date' => now()->isoFormat('D MMMM YYYY')])).'</text>';
        $parts[] = '<text x="96" y="'.(self::HEIGHT - 194).'" font-family="Helvetica,Arial,sans-serif" font-size="15" fill="#5C6B7E">'
            .$e(__('reports.cover_reported', ['reported' => $compiled['reported'], 'total' => $compiled['total']])).'</text>';
        $parts[] = '<text x="96" y="'.(self::HEIGHT - 168).'" font-family="Helvetica,Arial,sans-serif" font-size="14" fill="#0E5536">'
            .$e($this->permalink($report)).'</text>';

        return $this->svg($parts);
    }

    /** Page two: the figures, each with its period and its source. */
    public function body(Report $report): string
    {
        $compiled = $this->builder->compile($report);
        $range = $this->builder->range($compiled['rows']);
        $e = fn (?string $s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_XML1, 'UTF-8');

        $parts = ['<rect width="'.self::WIDTH.'" height="'.self::HEIGHT.'" fill="#FFFFFF"/>'];

        $parts[] = $this->mark(96, 68, 34);
        $parts[] = '<text x="146" y="94" font-family="Georgia,serif" font-size="18" font-weight="600" fill="#0F1B2D">'.$e($compiled['title']).'</text>';
        $parts[] = '<line x1="96" y1="126" x2="'.(self::WIDTH - 96).'" y2="126" stroke="#0F1B2D" stroke-width="2"/>';

        $top = 190;
        $rowHeight = 62;
        $barX = 660;
        $barWidth = 300;

        $parts[] = '<text x="96" y="'.($top - 26).'" font-family="Helvetica,Arial,sans-serif" font-size="12" font-weight="700" fill="#5C6B7E">'.$e(__('reports.col_subject')).'</text>';
        $parts[] = '<text x="500" y="'.($top - 26).'" font-family="Helvetica,Arial,sans-serif" font-size="12" font-weight="700" fill="#5C6B7E">'.$e(__('comparator.col_value')).'</text>';
        $parts[] = '<text x="'.$barX.'" y="'.($top - 26).'" font-family="Helvetica,Arial,sans-serif" font-size="12" font-weight="700" fill="#5C6B7E">'.$e(__('comparator.col_provenance')).'</text>';

        foreach ($compiled['rows']->values() as $i => $row) {
            $y = $top + ($i * $rowHeight);

            if ($y > self::HEIGHT - 200) {
                $parts[] = '<text x="96" y="'.($y + 6).'" font-family="Helvetica,Arial,sans-serif" font-size="13" font-style="italic" fill="#5C6B7E">'
                    .$e(__('reports.truncated', ['n' => $compiled['rows']->count() - $i])).'</text>';
                break;
            }

            $parts[] = '<text x="96" y="'.($y + 4).'" font-family="Helvetica,Arial,sans-serif" font-size="14" fill="#1F2937">'
                .$e(Str::limit($row['label'], 48)).'</text>';

            $parts[] = $row['reported']
                ? '<text x="500" y="'.($y + 4).'" font-family="Helvetica,Arial,sans-serif" font-size="17" font-weight="600" fill="#0F1B2D">'.$e($row['display']).'</text>'
                : '<text x="500" y="'.($y + 4).'" font-family="Helvetica,Arial,sans-serif" font-size="14" font-style="italic" fill="#5C6B7E">'.$e($row['display']).'</text>';

            // The bar, with its figure already stated beside it above.
            $parts[] = '<rect x="'.$barX.'" y="'.($y - 10).'" width="'.$barWidth.'" height="14" fill="#E5E0D5"/>';

            if ($row['numeric'] !== null && $range['max'] > $range['min']) {
                $share = ($row['numeric'] - $range['min']) / ($range['max'] - $range['min']);
                $parts[] = '<rect x="'.$barX.'" y="'.($y - 10).'" width="'.max(3, (int) round($share * $barWidth)).'" height="14" fill="#0E5536"/>';
            }

            if ($row['source']) {
                $parts[] = '<text x="96" y="'.($y + 24).'" font-family="Helvetica,Arial,sans-serif" font-size="11" fill="#5C6B7E">'
                    .$e(Str::limit($row['source'].($row['period'] ? ', '.$row['period'] : ''), 110)).'</text>';
            }

            $parts[] = '<line x1="96" y1="'.($y + 36).'" x2="'.(self::WIDTH - 96).'" y2="'.($y + 36).'" stroke="#E5E0D5" stroke-width="1"/>';
        }

        $footY = self::HEIGHT - 120;

        $parts[] = '<line x1="96" y1="'.$footY.'" x2="'.(self::WIDTH - 96).'" y2="'.$footY.'" stroke="#E5E0D5" stroke-width="1"/>';
        $parts[] = '<text x="96" y="'.($footY + 24).'" font-family="Helvetica,Arial,sans-serif" font-size="12" fill="#5C6B7E">'
            .$e(__('reports.foot_note', ['date' => now()->isoFormat('D MMMM YYYY')])).'</text>';
        $parts[] = '<text x="96" y="'.($footY + 44).'" font-family="Helvetica,Arial,sans-serif" font-size="12" fill="#0E5536">'
            .$e($this->permalink($report)).'</text>';

        return $this->svg($parts);
    }

    private function permalink(Report $report): string
    {
        return route('admin.reports.show', ['locale' => $report->locale, 'report' => $report->id]);
    }

    private function mark(int $x, int $y, int $size): string
    {
        $path = public_path('img/crasa-logo.svg');

        if (! is_readable($path)) {
            return '';
        }

        $svg = file_get_contents($path);
        $inner = preg_replace(['/^.*?<svg[^>]*>/s', '/<\/svg>.*$/s'], '', $svg);

        preg_match('/viewBox="([^"]+)"/', $svg, $box);
        [, , $w] = array_map('floatval', preg_split('/[\s,]+/', $box[1] ?? '0 0 48 48'));

        return '<g transform="translate('.$x.','.$y.') scale('.round($w > 0 ? $size / $w : 1, 4).')">'.$inner.'</g>';
    }

    /** @param list<string> $parts */
    private function svg(array $parts): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<svg xmlns="http://www.w3.org/2000/svg" width="'.self::WIDTH.'" height="'.self::HEIGHT.'" viewBox="0 0 '.self::WIDTH.' '.self::HEIGHT.'">'
            .implode('', $parts)
            .'</svg>';
    }

    /** @return list<string> */
    private function wrap(string $text, int $width): array
    {
        return explode("\n", wordwrap($text, $width, "\n", true));
    }
}

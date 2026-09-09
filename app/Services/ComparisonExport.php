<?php

namespace App\Services;

use App\Support\Comparison;
use App\Support\ComparisonCell;
use Illuminate\Support\Str;
use Imagick;
use RuntimeException;

/**
 * A comparison, as data and as a document.
 *
 * BUILD_SPEC 6.3 asks for a branded PDF and PNG carrying source, date and
 * permalink. This is the feature that puts CRASA's mark into a ministerial
 * brief, so the artefact has to look like a document rather than a
 * screenshot of a web page.
 *
 * It is drawn as SVG and rasterised by Imagick, which is already on this
 * host for PDF thumbnails. A headless browser would have been a third
 * service to run, secure and keep in step with the design, for a document
 * that is a table, a caption and a mark.
 *
 * THE DEMONSTRATION NOTICE TRAVELS WITH THE FILE. Screen 03: the notice
 * "remains in place, on screen and in every export, until each value is
 * confirmed by its national regulator". A PNG that leaves the platform
 * without it is a chart of unvalidated figures with CRASA's logo on it.
 */
final class ComparisonExport
{
    /** @return array<string, mixed> */
    public function payload(Comparison $comparison): array
    {
        $cells = $comparison->cells();

        return [
            'mode' => $comparison->mode,
            'category' => $comparison->category,
            'indicator' => $comparison->indicator?->slug,
            'permalink' => $comparison->permalink(),
            'sample' => $comparison->carriesSampleData(),
            'updated' => $comparison->latestUpdate()?->toIso8601String(),
            'countries' => $comparison->countries->map(fn ($country) => [
                'id' => $country->id,
                'code' => strtoupper($country->iso_code),
                'name' => $country->name,
                'authority' => $country->authorities->first()?->acronym,
            ])->values()->all(),
            'indicators' => $comparison->indicators->map(function ($indicator) use ($cells, $comparison) {
                [$min, $max] = $this->range($cells[$indicator->id] ?? []);

                return [
                    'id' => $indicator->id,
                    'slug' => $indicator->slug,
                    'name' => $indicator->name,
                    'unit' => $indicator->unitLabel(),
                    'type' => $indicator->data_type,
                    'source' => $indicator->source_standard,
                    'cells' => $comparison->countries->map(function ($country) use ($cells, $indicator, $min, $max) {
                        $cell = $cells[$indicator->id][$country->id] ?? null;

                        return $cell === null ? null : [
                            'country' => strtoupper($country->iso_code),
                            'display' => $cell->display(),
                            'reported' => $cell->isReported(),
                            'provenance' => $cell->provenance(),
                            'source' => $cell->source(),
                            'period' => $cell->period(),
                            'share' => $cell->share($min, $max),
                            'band' => $cell->band($min, $max),
                        ];
                    })->values()->all(),
                ];
            })->values()->all(),
        ];
    }

    /**
     * RP-08 CSV.
     *
     * One row per indicator per country, with the period and the source on
     * every row. A wide CSV with countries as columns loses the provenance,
     * and provenance is the reason a regulator opens the file.
     */
    public function csv(Comparison $comparison): string
    {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, [
            __('comparator.csv_indicator'),
            __('comparator.csv_unit'),
            __('comparator.csv_country'),
            __('comparator.csv_code'),
            __('comparator.csv_value'),
            __('comparator.csv_period'),
            __('comparator.csv_provenance'),
            __('comparator.csv_source'),
        ]);

        foreach ($comparison->cells() as $row) {
            foreach ($row as $cell) {
                fputcsv($handle, [
                    $cell->indicator->name,
                    $cell->indicator->unitLabel(),
                    $cell->country->name,
                    strtoupper($cell->country->iso_code),
                    // The words, not a blank. A CSV opened in a spreadsheet
                    // turns an empty cell into a zero the moment anyone sums
                    // the column.
                    $cell->display(),
                    $cell->period() ?? '',
                    __('comparator.provenance_'.$cell->provenance()),
                    $cell->source() ?? '',
                ]);
            }
        }

        // The notice is a row of the file, not a header a spreadsheet drops.
        if ($comparison->carriesSampleData()) {
            fputcsv($handle, []);
            fputcsv($handle, [__('comparator.sample_notice_short')]);
        }

        fputcsv($handle, []);
        fputcsv($handle, [__('comparator.csv_permalink'), $comparison->permalink()]);
        fputcsv($handle, [__('comparator.csv_generated'), now()->toDayDateTimeString()]);

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        // A byte order mark, so Excel opens the accented member state names
        // correctly rather than as mojibake. Every name in this file is one
        // of fourteen and six of them carry accents.
        return "\u{FEFF}".$csv;
    }

    public function png(Comparison $comparison): string
    {
        return $this->rasterise($comparison, 'png');
    }

    public function pdf(Comparison $comparison): string
    {
        return $this->rasterise($comparison, 'pdf');
    }

    public function filename(Comparison $comparison, string $extension): string
    {
        $subject = $comparison->mode === Comparison::BROWSE
            ? ($comparison->indicator?->slug ?? 'comparison')
            : ($comparison->category.'-'.$comparison->countries->pluck('iso_code')->implode('-'));

        return 'crasa-'.Str::slug($subject).'-'.now()->format('Y-m-d').'.'.$extension;
    }

    /**
     * The document itself.
     *
     * Deliberately a document and not a screenshot: a masthead with the
     * mark, a title, the table, and a footer carrying the source, the date
     * and the permalink, on white, at a print size.
     */
    public function svg(Comparison $comparison): string
    {
        $cells = $comparison->cells();
        $countries = $comparison->countries;
        $indicators = $comparison->indicators;

        $columnCount = max(1, $countries->count());
        $width = 1240;
        $labelWidth = 380;
        $columnWidth = (int) (($width - $labelWidth - 80) / $columnCount);
        $rowHeight = 64;
        $top = 208;
        $height = $top + ($indicators->count() * $rowHeight) + 150;

        $e = fn (?string $s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $parts = [];

        $parts[] = '<rect width="'.$width.'" height="'.$height.'" fill="#FFFFFF"/>';

        // Masthead. Navy band, the mark, the wordmark.
        $parts[] = '<rect x="0" y="0" width="'.$width.'" height="96" fill="#0F1B2D"/>';
        $parts[] = $this->mark(40, 26);
        $parts[] = '<text x="104" y="46" font-family="Georgia,serif" font-size="24" font-weight="600" fill="#FFFFFF">'.$e(__('site.acronym')).'</text>';
        $parts[] = '<text x="104" y="70" font-family="Helvetica,Arial,sans-serif" font-size="13" fill="#E5E0D5">'.$e(__('site.full_name')).'</text>';

        $title = $comparison->mode === Comparison::BROWSE
            ? (string) $comparison->indicator?->name
            : __('comparator.category_'.$comparison->category);

        $parts[] = '<text x="40" y="146" font-family="Georgia,serif" font-size="28" font-weight="600" fill="#0F1B2D">'.$e($title).'</text>';
        $parts[] = '<text x="40" y="172" font-family="Helvetica,Arial,sans-serif" font-size="14" fill="#5C6B7E">'
            .$e($countries->pluck('name')->implode(', ')).'</text>';

        // Column headings.
        $parts[] = '<line x1="40" y1="'.($top - 26).'" x2="'.($width - 40).'" y2="'.($top - 26).'" stroke="#E5E0D5" stroke-width="1"/>';

        foreach ($countries->values() as $i => $country) {
            $x = $labelWidth + ($i * $columnWidth) + 12;
            $parts[] = '<text x="'.$x.'" y="'.($top - 38).'" font-family="Helvetica,Arial,sans-serif" font-size="13" font-weight="700" fill="#0F1B2D">'
                .$e(strtoupper($country->iso_code)).'</text>';
            $parts[] = '<text x="'.($x + 34).'" y="'.($top - 38).'" font-family="Helvetica,Arial,sans-serif" font-size="13" fill="#1F2937">'
                .$e(Str::limit($country->name, 18)).'</text>';
        }

        foreach ($indicators->values() as $r => $indicator) {
            $y = $top + ($r * $rowHeight);

            $parts[] = '<text x="40" y="'.($y + 6).'" font-family="Helvetica,Arial,sans-serif" font-size="14" font-weight="600" fill="#1F2937">'
                .$e(Str::limit($indicator->name, 46)).'</text>';
            $parts[] = '<text x="40" y="'.($y + 26).'" font-family="Helvetica,Arial,sans-serif" font-size="12" fill="#5C6B7E">'
                .$e((string) $indicator->unitLabel()).'</text>';

            foreach ($countries->values() as $i => $country) {
                $cell = $cells[$indicator->id][$country->id] ?? null;

                if ($cell === null) {
                    continue;
                }

                $x = $labelWidth + ($i * $columnWidth) + 12;

                // Not reported reads as words in slate italic, never as a
                // gap and never as a zero.
                $parts[] = $cell->isReported()
                    ? '<text x="'.$x.'" y="'.($y + 6).'" font-family="Helvetica,Arial,sans-serif" font-size="19" font-weight="600" fill="#0F1B2D">'.$e($cell->display()).'</text>'
                    : '<text x="'.$x.'" y="'.($y + 6).'" font-family="Helvetica,Arial,sans-serif" font-size="15" font-style="italic" fill="#5C6B7E">'.$e($cell->display()).'</text>';

                if ($cell->period()) {
                    $parts[] = '<text x="'.$x.'" y="'.($y + 26).'" font-family="Helvetica,Arial,sans-serif" font-size="11" fill="#5C6B7E">'.$e($cell->period()).'</text>';
                }
            }

            $parts[] = '<line x1="40" y1="'.($y + 38).'" x2="'.($width - 40).'" y2="'.($y + 38).'" stroke="#E5E0D5" stroke-width="1"/>';
        }

        $footY = $height - 96;

        if ($comparison->carriesSampleData()) {
            $parts[] = '<rect x="40" y="'.($footY - 44).'" width="'.($width - 80).'" height="36" fill="#FDF0DC" stroke="#E5BE87"/>';
            $parts[] = '<rect x="40" y="'.($footY - 44).'" width="4" height="36" fill="#C2710C"/>';
            $parts[] = '<text x="56" y="'.($footY - 20).'" font-family="Helvetica,Arial,sans-serif" font-size="13" fill="#5C3404">'
                .$e(__('comparator.sample_notice_short')).'</text>';
        }

        $parts[] = '<line x1="40" y1="'.$footY.'" x2="'.($width - 40).'" y2="'.$footY.'" stroke="#E5E0D5" stroke-width="1"/>';
        $parts[] = '<text x="40" y="'.($footY + 22).'" font-family="Helvetica,Arial,sans-serif" font-size="12" fill="#5C6B7E">'
            .$e(__('comparator.export_source', ['date' => now()->isoFormat('D MMMM YYYY')])).'</text>';
        $parts[] = '<text x="40" y="'.($footY + 42).'" font-family="Helvetica,Arial,sans-serif" font-size="12" fill="#0E5536">'
            .$e($comparison->permalink()).'</text>';

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'">'
            .implode('', $parts)
            .'</svg>';
    }

    /** The CRASA mark, read from the same file the site serves. */
    private function mark(int $x, int $y): string
    {
        $path = public_path('img/crasa-logo.svg');

        if (! is_readable($path)) {
            return '';
        }

        $svg = file_get_contents($path);
        $inner = preg_replace(['/^.*?<svg[^>]*>/s', '/<\/svg>.*$/s'], '', $svg);

        preg_match('/viewBox="([^"]+)"/', $svg, $box);
        $view = $box[1] ?? '0 0 48 48';
        [, , $w, $h] = array_map('floatval', preg_split('/[\s,]+/', $view));

        $scale = $w > 0 ? 48 / $w : 1;

        return '<g transform="translate('.$x.','.$y.') scale('.round($scale, 4).')">'.$inner.'</g>';
    }

    private function rasterise(Comparison $comparison, string $format): string
    {
        if (! extension_loaded('imagick')) {
            throw new RuntimeException('Imagick is required to export a comparison as '.$format.'.');
        }

        $image = new Imagick;
        $image->setBackgroundColor('white');
        $image->readImageBlob($this->svg($comparison));
        $image->setImageFormat($format);

        if ($format === 'png') {
            $image->setImageResolution(144, 144);
        }

        $blob = $image->getImagesBlob();
        $image->clear();

        return $blob;
    }

    /**
     * The lowest and highest reported figure in a row, for the bars and the
     * choropleth bands.
     *
     * @param  array<int, ComparisonCell>  $row
     * @return array{0: float, 1: float}
     */
    private function range(array $row): array
    {
        $numbers = [];

        foreach ($row as $cell) {
            if ($cell->isReported() && $cell->value->value_numeric !== null) {
                $numbers[] = (float) $cell->value->value_numeric;
            }
        }

        return $numbers === [] ? [0.0, 1.0] : [min($numbers), max($numbers)];
    }
}

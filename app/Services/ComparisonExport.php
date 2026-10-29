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

    /**
     * The same rows as the CSV, as an Excel workbook. Values that are
     * figures are written as numbers so a reader can sort and sum them;
     * "Not reported" stays words.
     */
    public function xlsx(Comparison $comparison): string
    {
        $rows = [[
            __('comparator.csv_indicator'), __('comparator.csv_unit'), __('comparator.csv_country'),
            __('comparator.csv_code'), __('comparator.csv_value'), __('comparator.csv_period'),
            __('comparator.csv_provenance'), __('comparator.csv_source'),
        ]];

        $cells = $comparison->indicator !== null ? $comparison->ranked()->all() : collect($comparison->cells())->flatten()->all();

        foreach ($cells as $cell) {
            $numeric = $cell->isReported() && $cell->value->value_numeric !== null;
            $rows[] = [
                $cell->indicator->name,
                (string) $cell->indicator->unitLabel(),
                $cell->country->name,
                strtoupper($cell->country->iso_code),
                $numeric ? (float) $cell->value->value_numeric : $cell->display(),
                (string) ($cell->period() ?? ''),
                __('comparator.provenance_'.$cell->provenance()),
                (string) ($cell->source() ?? ''),
            ];
        }

        $rows[] = [];
        $rows[] = [__('comparator.csv_permalink'), $comparison->permalink()];
        $rows[] = [__('comparator.csv_generated'), now()->toDayDateTimeString()];

        return \App\Support\SimpleXlsx::build($rows, 'CRASA comparison');
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
            : ($comparison->indicator?->slug.'-'.$comparison->countries->pluck('iso_code')->implode('-'));

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
        // Day 2: both modes are one indicator, drawn as ranked bars with
        // the table beside them. The category matrix stays for any caller
        // that builds a comparison without an indicator.
        return $comparison->indicator !== null
            ? $this->browseSvg($comparison)
            : $this->compareSvg($comparison);
    }

    /**
     * Compare mode: up to four member states as columns, the category's
     * indicators as rows.
     */
    private function compareSvg(Comparison $comparison): string
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

        $sources = $this->sources($comparison);
        $notes = $this->notes($comparison);

        $sourcesHeight = $sources === [] ? 0 : 40 + (count($sources) * 20);
        $notesHeight = count($notes) * 20;
        $height = $top + ($indicators->count() * $rowHeight) + $notesHeight + $sourcesHeight + 150;

        $e = $this->escaper();
        $parts = $this->masthead($width, $height);

        $parts[] = '<text x="40" y="146" font-family="Georgia,serif" font-size="28" font-weight="600" fill="'.self::INK.'">'
            .$e(__('comparator.category_'.$comparison->category)).'</text>';
        $parts[] = '<text x="40" y="172" font-family="Helvetica,Arial,sans-serif" font-size="14" fill="'.self::MUTED.'">'
            .$e($countries->pluck('name')->implode(', ')).'</text>';

        // Column headings.
        $parts[] = '<line x1="40" y1="'.($top - 26).'" x2="'.($width - 40).'" y2="'.($top - 26).'" stroke="'.self::RULE.'" stroke-width="1"/>';

        foreach ($countries->values() as $i => $country) {
            $x = $labelWidth + ($i * $columnWidth) + 12;
            $parts[] = '<text x="'.$x.'" y="'.($top - 38).'" font-family="Helvetica,Arial,sans-serif" font-size="13" font-weight="700" fill="'.self::INK.'">'
                .$e(strtoupper($country->iso_code)).'</text>';
            $parts[] = '<text x="'.($x + 34).'" y="'.($top - 38).'" font-family="Helvetica,Arial,sans-serif" font-size="13" fill="'.self::INK.'">'
                .$e(Str::limit($country->name, 18)).'</text>';
        }

        foreach ($indicators->values() as $r => $indicator) {
            $y = $top + ($r * $rowHeight);

            $parts[] = '<text x="40" y="'.($y + 6).'" font-family="Helvetica,Arial,sans-serif" font-size="14" font-weight="600" fill="'.self::INK.'">'
                .$e(Str::limit($indicator->name, 46)).'</text>';
            $parts[] = '<text x="40" y="'.($y + 26).'" font-family="Helvetica,Arial,sans-serif" font-size="12" fill="'.self::MUTED.'">'
                .$e((string) $indicator->unitLabel()).'</text>';

            foreach ($countries->values() as $i => $country) {
                $cell = $cells[$indicator->id][$country->id] ?? null;

                if ($cell === null) {
                    continue;
                }

                $x = $labelWidth + ($i * $columnWidth) + 12;
                $parts[] = $this->value($cell, $x, $y + 6, 'start');

                $note = $cell->source() !== null ? array_search($cell->source(), $sources, true) + 1 : null;
                $meta = trim(($cell->period() ?? '').($note !== null ? '  ['.$note.']' : ''));

                if ($meta !== '') {
                    $parts[] = '<text x="'.$x.'" y="'.($y + 26).'" font-family="Helvetica,Arial,sans-serif" font-size="11" fill="'.self::MUTED.'">'.$e($meta).'</text>';
                }
            }

            $parts[] = '<line x1="40" y1="'.($y + 38).'" x2="'.($width - 40).'" y2="'.($y + 38).'" stroke="'.self::RULE.'" stroke-width="1"/>';
        }

        $after = $top + ($indicators->count() * $rowHeight) + 20;
        $parts = array_merge($parts, $this->noteLines($notes, $after), $this->sourceList($sources, $after + $notesHeight));

        return $this->document($comparison, $parts, $width, $height);
    }

    /**
     * Browse mode: one indicator down all fourteen member states.
     *
     * A row per member state, not a column: fourteen columns at a print
     * width left each figure fifty pixels and the names ran into each
     * other, so the exported page could not be read.
     */
    private function browseSvg(Comparison $comparison): string
    {
        $cells = $comparison->cells();
        $indicator = $comparison->indicator;
        $row = $indicator ? ($cells[$indicator->id] ?? []) : [];
        $countries = $comparison->ranked()->map(fn ($cell) => $cell->country)->values();
        $scale = $comparison->scaleMax();

        $width = 1240;
        $rowHeight = 34;
        $top = 236;

        $sources = $this->sources($comparison);
        $notes = $this->notes($comparison);

        $sourcesHeight = $sources === [] ? 0 : 40 + (count($sources) * 20);
        $notesHeight = count($notes) * 20;
        $tableHeight = $countries->count() * $rowHeight;
        $height = $top + $tableHeight + $notesHeight + $sourcesHeight + 150;

        // Column positions: member state, value (right aligned), period,
        // source note, provenance.
        $xState = 40;
        $xValue = 640;
        $xPeriod = 690;
        $xNote = 790;
        $xProvenance = 880;

        $e = $this->escaper();
        $parts = $this->masthead($width, $height);

        $parts[] = '<text x="40" y="146" font-family="Georgia,serif" font-size="28" font-weight="600" fill="'.self::INK.'">'
            .$e(Str::limit((string) $indicator?->name, 80)).'</text>';
        $parts[] = '<text x="40" y="172" font-family="Helvetica,Arial,sans-serif" font-size="14" fill="'.self::MUTED.'">'
            .$e(trim(Str::ucfirst((string) $indicator?->unitLabel()).'. '.($comparison->mode === Comparison::BROWSE ? __('comparator.export_browse_scope') : $comparison->countries->pluck('name')->implode(', ')), '. ')).'</text>';

        $head = $top - 30;
        $heading = fn (int $x, string $label, string $anchor = 'start') => '<text x="'.$x.'" y="'.$head.'" text-anchor="'.$anchor.'" font-family="Helvetica,Arial,sans-serif" font-size="12" font-weight="700" fill="'.self::INK.'">'.$e($label).'</text>';

        $parts[] = $heading($xState, __('comparator.col_state'));
        $parts[] = $heading($xValue, __('comparator.col_value'), 'end');
        $parts[] = $heading($xPeriod, __('comparator.csv_period'));
        $parts[] = $heading($xNote, __('comparator.csv_source'));
        $parts[] = $heading($xProvenance, __('comparator.col_provenance'));
        $parts[] = '<line x1="40" y1="'.($head + 10).'" x2="'.($width - 40).'" y2="'.($head + 10).'" stroke="'.self::INK.'" stroke-width="1"/>';

        foreach ($countries as $r => $country) {
            $cell = $row[$country->id];
            $y = $top + ($r * $rowHeight);

            $parts[] = '<text x="'.$xState.'" y="'.$y.'" font-family="Helvetica,Arial,sans-serif" font-size="13" font-weight="700" fill="'.self::INK.'">'
                .$e(strtoupper($country->iso_code)).'</text>';
            $parts[] = '<text x="'.($xState + 36).'" y="'.$y.'" font-family="Helvetica,Arial,sans-serif" font-size="14" fill="'.self::INK.'">'
                .$e($country->name).'</text>';
            $parts[] = $this->value($cell, $xValue, $y, 'end', 16);

            if ($cell->isReported() && $cell->value->value_numeric !== null) {
                $w = max(3, round(((float) $cell->value->value_numeric / $scale) * 290, 1));
                $parts[] = '<rect x="250" y="'.($y - 14).'" width="'.$w.'" height="18" rx="2" fill="'.self::GREEN.'"/>';
            } else {
                $parts[] = '<rect x="250" y="'.($y - 14).'" width="30" height="18" rx="2" fill="#EEF2FA" stroke="#7B8794" stroke-dasharray="3 2"/>';
            }

            if ($cell->period() !== null) {
                $parts[] = '<text x="'.$xPeriod.'" y="'.$y.'" font-family="Helvetica,Arial,sans-serif" font-size="13" fill="'.self::MUTED.'">'.$e($cell->period()).'</text>';
            }

            if ($cell->source() !== null) {
                $parts[] = '<text x="'.$xNote.'" y="'.$y.'" font-family="Helvetica,Arial,sans-serif" font-size="13" fill="'.self::MUTED.'">'
                    .$e('['.(array_search($cell->source(), $sources, true) + 1).']').'</text>';
            }

            $parts[] = '<text x="'.$xProvenance.'" y="'.$y.'" font-family="Helvetica,Arial,sans-serif" font-size="13" fill="'.self::MUTED.'">'
                .$e(__('comparator.provenance_'.$cell->provenance())).'</text>';
            $parts[] = '<line x1="40" y1="'.($y + 12).'" x2="'.($width - 40).'" y2="'.($y + 12).'" stroke="'.self::RULE.'" stroke-width="1"/>';
        }

        $after = $top + $tableHeight + 10;
        $parts = array_merge($parts, $this->noteLines($notes, $after), $this->sourceList($sources, $after + $notesHeight));

        return $this->document($comparison, $parts, $width, $height);
    }

    /*
     * Document colours. The export is a file, not a page, so it cannot read
     * the token layer; these are the light theme's ink, muted text, rule and
     * link green.
     */
    private const INK = '#25384A';

    private const MUTED = '#55687A';

    private const RULE = '#D9E3EC';

    private const GREEN = '#0F8757';

    private const LINK = '#0C7A4E';

    /** @return \Closure(?string): string */
    private function escaper(): \Closure
    {
        return fn (?string $s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /**
     * White ground, the mark, the wordmark and a green rule under them.
     *
     * @return list<string>
     */
    private function masthead(int $width, int $height): array
    {
        $e = $this->escaper();

        return [
            '<rect width="'.$width.'" height="'.$height.'" fill="#FFFFFF"/>',
            $this->mark(40, 26),
            '<text x="104" y="46" font-family="Georgia,serif" font-size="24" font-weight="600" fill="'.self::INK.'">'.$e(__('site.acronym')).'</text>',
            '<text x="104" y="70" font-family="Helvetica,Arial,sans-serif" font-size="13" fill="'.self::MUTED.'">'.$e(__('site.full_name')).'</text>',
            '<rect x="40" y="94" width="'.($width - 80).'" height="3" fill="'.self::GREEN.'"/>',
        ];
    }

    /** One figure: words in muted italic when not reported, never a gap. */
    private function value(ComparisonCell $cell, int $x, int $y, string $anchor, int $size = 19): string
    {
        $e = $this->escaper();

        return $cell->isReported()
            ? '<text x="'.$x.'" y="'.$y.'" text-anchor="'.$anchor.'" font-family="Helvetica,Arial,sans-serif" font-size="'.$size.'" font-weight="600" fill="'.self::INK.'">'.$e($cell->display()).'</text>'
            : '<text x="'.$x.'" y="'.$y.'" text-anchor="'.$anchor.'" font-family="Helvetica,Arial,sans-serif" font-size="'.($size - 4).'" font-style="italic" fill="'.self::MUTED.'">'.$e($cell->display()).'</text>';
    }

    /**
     * Every reported figure's source, numbered once each. The footer says
     * each figure carries its source; a footnote number beside the figure,
     * and the list under the table, keep a long source from being cut to
     * fit a column.
     *
     * @return list<string>
     */
    private function sources(Comparison $comparison): array
    {
        $cells = $comparison->cells();
        $sources = [];

        foreach ($comparison->indicators as $indicator) {
            foreach ($comparison->countries as $country) {
                $source = ($cells[$indicator->id][$country->id] ?? null)?->source();
                if ($source !== null && ! in_array($source, $sources, true)) {
                    $sources[] = $source;
                }
            }
        }

        return $sources;
    }

    /**
     * Reading notes the indicators carry, such as subscriptions per 100
     * inhabitants running above 100. The same sentence as on screen.
     *
     * @return list<string>
     */
    private function notes(Comparison $comparison): array
    {
        return $comparison->indicators
            ->filter(fn ($indicator) => $indicator->canExceedHundred())
            ->isEmpty() ? [] : [__('comparator.note_above_100')];
    }

    /**
     * @param  list<string>  $notes
     * @return list<string>
     */
    private function noteLines(array $notes, int $y): array
    {
        $e = $this->escaper();
        $lines = [];

        foreach ($notes as $n => $note) {
            $lines[] = '<text x="40" y="'.($y + 10 + ($n * 20)).'" font-family="Helvetica,Arial,sans-serif" font-size="12" font-style="italic" fill="'.self::INK.'">'.$e($note).'</text>';
        }

        return $lines;
    }

    /**
     * @param  list<string>  $sources
     * @return list<string>
     */
    private function sourceList(array $sources, int $y): array
    {
        if ($sources === []) {
            return [];
        }

        $e = $this->escaper();
        $lines = ['<text x="40" y="'.($y + 20).'" font-family="Helvetica,Arial,sans-serif" font-size="12" font-weight="700" fill="'.self::INK.'">'
            .$e(__('comparator.csv_source')).'</text>'];

        foreach ($sources as $n => $source) {
            $lines[] = '<text x="40" y="'.($y + 40 + ($n * 20)).'" font-family="Helvetica,Arial,sans-serif" font-size="11" fill="'.self::MUTED.'">'
                .$e('['.($n + 1).'] '.Str::limit($source, 170)).'</text>';
        }

        return $lines;
    }

    /**
     * The footer (date and permalink) and the SVG wrapper.
     *
     * @param  list<string>  $parts
     */
    private function document(Comparison $comparison, array $parts, int $width, int $height): string
    {
        $e = $this->escaper();
        $footY = $height - 96;

        $parts[] = '<line x1="40" y1="'.$footY.'" x2="'.($width - 40).'" y2="'.$footY.'" stroke="'.self::RULE.'" stroke-width="1"/>';
        $parts[] = '<text x="40" y="'.($footY + 22).'" font-family="Helvetica,Arial,sans-serif" font-size="12" fill="'.self::MUTED.'">'
            .$e(__('comparator.export_source', ['date' => now()->isoFormat('D MMMM YYYY')])).'</text>';
        $parts[] = '<text x="40" y="'.($footY + 42).'" font-family="Helvetica,Arial,sans-serif" font-size="12" fill="'.self::LINK.'">'
            .$e($comparison->permalink()).'</text>';

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'">'
            .implode('', $parts)
            .'</svg>';
    }

    /**
     * The CRASA mark, as the raster the site's app icon uses.
     *
     * The vector logo fills its globe with a gradient set in a style
     * attribute, which ImageMagick's built-in SVG renderer (this host has no
     * librsvg) paints solid black: the exported PDF carried a black disc
     * where the mark should be. An embedded PNG renders the same everywhere.
     */
    private function mark(int $x, int $y): string
    {
        $path = public_path('img/app/icon-192.png');

        if (! is_readable($path)) {
            return '';
        }

        return '<image x="'.$x.'" y="'.$y.'" width="48" height="48" xlink:href="data:image/png;base64,'
            .base64_encode((string) file_get_contents($path)).'"/>';
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

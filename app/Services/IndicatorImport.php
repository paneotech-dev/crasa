<?php

namespace App\Services;

use App\Models\Indicator;
use App\Models\Submission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Bulk import and export of indicator values, RP-08 and BUILD_SPEC 6.2.
 *
 * The TOR asked for Kobo, ODK or Excel-class data collection, so this is
 * fulfilment rather than extra. And realistically a national regulator will
 * send a spreadsheet whatever the platform offers: making that path
 * official is the difference between a validated import and somebody in
 * Gaborone retyping fourteen returns by hand.
 *
 * THE IMPORT IS TWO STEPS, AND THAT IS THE POINT. An upload is checked and
 * reported on; nothing is written. Only a confirmation writes, and it
 * writes every row or none. A half-imported dataset is worse than a
 * rejected one, because nobody can tell which half is real.
 */
final class IndicatorImport
{
    /** The columns of the template, in order. */
    public const COLUMNS = ['indicator', 'slug', 'unit', 'value', 'source_note'];

    /**
     * A template for one category, or for everything.
     *
     * Pre-filled with the indicator names, slugs and units, so the person
     * filling it in never has to know a slug and cannot invent one. The
     * slug column is what the import matches on; the name is there to be
     * read.
     */
    public function template(?string $category = null): string
    {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, array_map(fn ($c) => __('import.col_'.$c), self::COLUMNS));

        $indicators = Indicator::active()
            ->when($category, fn ($q) => $q->where('category', $category))
            ->orderBy('display_order')
            ->get();

        foreach ($indicators as $indicator) {
            fputcsv($handle, [$indicator->name, $indicator->slug, $indicator->unitLabel(), '', '']);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        // Excel needs the byte order mark to read the accented indicator
        // names correctly rather than as mojibake.
        return "\u{FEFF}".$csv;
    }

    /**
     * Read a file and report on it. Nothing is written.
     *
     * @return array{rows: Collection<int, array<string, mixed>>, errors: Collection<int, array<string, mixed>>}
     */
    public function inspect(UploadedFile $file, Submission $submission): array
    {
        $indicators = Indicator::active()->get()->keyBy('slug');
        $rows = collect();
        $errors = collect();

        $handle = fopen($file->getRealPath(), 'r');
        $header = null;
        $number = 0;

        while (($line = fgetcsv($handle)) !== false) {
            $number++;

            if ($header === null) {
                // Strip the byte order mark a spreadsheet writes back.
                $line[0] = preg_replace('/^\x{FEFF}/u', '', (string) $line[0]);
                $header = array_map(fn ($h) => strtolower(trim((string) $h)), $line);

                continue;
            }

            if (count(array_filter($line, fn ($cell) => trim((string) $cell) !== '')) === 0) {
                continue;
            }

            $row = $this->readRow($header, $line);
            $slug = trim((string) ($row['slug'] ?? ''));

            if ($slug === '') {
                $errors->push($this->error($number, 'slug', __('import.error_no_slug')));

                continue;
            }

            $indicator = $indicators->get($slug);

            if ($indicator === null) {
                $errors->push($this->error($number, 'slug', __('import.error_unknown_slug', ['slug' => $slug])));

                continue;
            }

            $value = trim((string) ($row['value'] ?? ''));

            if ($value === '') {
                // An empty row is not an error. It means the member state
                // has nothing to report for that indicator, and "Not
                // reported" is a legitimate answer.
                continue;
            }

            $numeric = in_array($indicator->data_type, ['numeric', 'percentage', 'currency', 'year'], true);

            if ($numeric && ! is_numeric($value)) {
                $errors->push($this->error(
                    $number,
                    'value',
                    __('import.error_not_a_number', ['unit' => $indicator->unitLabel(), 'found' => $value]),
                    $indicator->name,
                ));

                continue;
            }

            if ($indicator->data_type === 'boolean' && ! in_array(strtolower($value), ['0', '1', 'yes', 'no', 'true', 'false'], true)) {
                $errors->push($this->error($number, 'value', __('import.error_not_a_boolean', ['found' => $value]), $indicator->name));

                continue;
            }

            $source = trim((string) ($row['source_note'] ?? ''));

            if ($source === '') {
                // The same rule as the form: a figure nobody can attribute
                // cannot be defended when a member state queries it.
                $errors->push($this->error($number, 'source_note', __('import.error_no_source'), $indicator->name));

                continue;
            }

            $rows->push([
                'line' => $number,
                'indicator' => $indicator,
                'numeric' => $numeric || $indicator->data_type === 'boolean',
                'value' => $this->normalise($indicator, $value),
                'source_note' => $source,
            ]);
        }

        fclose($handle);

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * Write an inspected file into a return.
     *
     * One transaction. Either every row lands or none does, which is the
     * whole reason the import is two steps: a partial import leaves a
     * dataset nobody can trust and no way to tell which figures came from
     * the file.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    public function commit(Submission $submission, Collection $rows): int
    {
        return DB::transaction(function () use ($submission, $rows) {
            foreach ($rows as $row) {
                $submission->values()->updateOrCreate(
                    ['indicator_id' => $row['indicator']->id],
                    [
                        'value_numeric' => $row['numeric'] ? $row['value'] : null,
                        'value_text' => $row['numeric'] ? null : $row['value'],
                        'source_note' => $row['source_note'],
                    ]
                );
            }

            return $rows->count();
        });
    }

    /** RP-08 export, as CSV. */
    public function exportCsv(Submission $submission): string
    {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, array_map(fn ($c) => __('import.col_'.$c), self::COLUMNS));

        foreach ($this->exportRows($submission) as $row) {
            fputcsv($handle, [$row['indicator'], $row['slug'], $row['unit'], $row['value'], $row['source_note']]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return "\u{FEFF}".$csv;
    }

    /** RP-08 export, as JSON. */
    public function exportJson(Submission $submission): string
    {
        return json_encode([
            'country' => $submission->country?->iso_code,
            'period' => $submission->period,
            'status' => $submission->status->value,
            'values' => $this->exportRows($submission),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @return array<int, array<string, mixed>> */
    private function exportRows(Submission $submission): array
    {
        $values = $submission->values->keyBy('indicator_id');
        $rows = [];

        foreach (Indicator::active()->orderBy('display_order')->get() as $indicator) {
            $value = $values->get($indicator->id);

            $rows[] = [
                'indicator' => $indicator->name,
                'slug' => $indicator->slug,
                'unit' => $indicator->unit,
                // An exported blank would come back as a zero the moment
                // anyone totals the column, so a missing figure says so.
                'value' => $value?->hasFigure()
                    ? ($value->value_numeric !== null ? rtrim(rtrim((string) $value->value_numeric, '0'), '.') : $value->value_text)
                    : __('comparator.not_reported'),
                'source_note' => $value?->source_note ?? '',
            ];
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    private function error(int $line, string $column, string $reason, ?string $indicator = null): array
    {
        /*
         * Row, column and reason. CLAUDE.md's own example of a good error
         * is exactly this shape: "Row 14: mobile termination rate must be a
         * number in local currency. Found 'n/a'." An error that says
         * "import failed" makes somebody open the file and guess.
         */
        return [
            'line' => $line,
            'column' => __('import.col_'.$column),
            'indicator' => $indicator,
            'reason' => $reason,
        ];
    }

    /** @param array<int, string> $header */
    private function readRow(array $header, array $line): array
    {
        $row = [];

        foreach ($header as $index => $name) {
            $key = $this->columnFor($name);

            if ($key !== null) {
                $row[$key] = $line[$index] ?? null;
            }
        }

        return $row;
    }

    /**
     * Match a header cell to a column.
     *
     * By the translated heading or by the bare key, so a template
     * downloaded in French and filled in there imports without anyone
     * having to know that the English word was the real one.
     */
    private function columnFor(string $heading): ?string
    {
        foreach (self::COLUMNS as $column) {
            if ($heading === $column) {
                return $column;
            }

            foreach (['en', 'fr', 'pt'] as $locale) {
                if ($heading === strtolower(__('import.col_'.$column, [], $locale))) {
                    return $column;
                }
            }
        }

        return null;
    }

    private function normalise(Indicator $indicator, string $value): string
    {
        if ($indicator->data_type !== 'boolean') {
            return $value;
        }

        return in_array(strtolower($value), ['1', 'yes', 'true'], true) ? '1' : '0';
    }
}

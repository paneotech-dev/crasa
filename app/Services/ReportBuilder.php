<?php

namespace App\Services;

use App\Models\Country;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\Report;
use Illuminate\Support\Collection;

/**
 * A regional brief, RP-12.
 *
 * One click on a member state or on an indicator. The output has to look
 * like a document rather than a screenshot of a web page, because it ends
 * up attached to a ministerial briefing and printed by somebody who was
 * not in the room.
 *
 * EVERY FIGURE CARRIES ITS SOURCE AND ITS DATE. That is not decoration on
 * a brief: it is the first question anyone senior asks, and a table of
 * unattributed numbers with CRASA's mark on it is worse than no brief.
 */
final class ReportBuilder
{
    /**
     * Everything the brief renders, whichever way it is output.
     *
     * @return array{
     *   subject: string, title: string, subtitle: string,
     *   rows: Collection<int, array<string, mixed>>,
     *   sample: bool, reported: int, total: int, period: string|null
     * }
     */
    public function compile(Report $report): array
    {
        return $report->isAboutCountry()
            ? $this->forCountry($report)
            : $this->forIndicator($report);
    }

    /** A member state against every indicator. */
    private function forCountry(Report $report): array
    {
        $country = $report->country();

        if ($country === null) {
            return $this->empty($report);
        }

        $values = IndicatorValue::query()
            ->where('country_id', $country->id)
            ->when($report->period, fn ($q) => $q->where('period', $report->period))
            ->orderBy('period')
            ->get()
            ->groupBy('indicator_id')
            ->map(fn (Collection $group) => $group->last());

        $rows = Indicator::active()
            ->orderBy('display_order')
            ->get()
            ->map(fn (Indicator $indicator) => $this->row($indicator, $values->get($indicator->id), $indicator->name));

        return [
            'subject' => Report::COUNTRY,
            'title' => $report->title,
            'subtitle' => trim(($country->authorities->first()?->name ?? '').($report->period ? ', '.$report->period : '')),
            'rows' => $rows,
            'sample' => $rows->contains(fn (array $r) => $r['sample']),
            'reported' => $rows->where('reported', true)->count(),
            'total' => $rows->count(),
            'period' => $report->period,
        ];
    }

    /** An indicator across all fourteen member states. */
    private function forIndicator(Report $report): array
    {
        $indicator = $report->indicator();

        if ($indicator === null) {
            return $this->empty($report);
        }

        $values = IndicatorValue::query()
            ->where('indicator_id', $indicator->id)
            ->when($report->period, fn ($q) => $q->where('period', $report->period))
            ->orderBy('period')
            ->get()
            ->groupBy('country_id')
            ->map(fn (Collection $group) => $group->last());

        $rows = Country::orderBy('name')
            ->get()
            ->map(fn (Country $country) => $this->row($indicator, $values->get($country->id), $country->name));

        return [
            'subject' => Report::INDICATOR,
            'title' => $report->title,
            'subtitle' => trim(($indicator->unitLabel() ?? '').($report->period ? ', '.$report->period : '')),
            'rows' => $rows,
            'sample' => $rows->contains(fn (array $r) => $r['sample']),
            'reported' => $rows->where('reported', true)->count(),
            'total' => $rows->count(),
            'period' => $report->period,
        ];
    }

    /** @return array<string, mixed> */
    private function row(Indicator $indicator, ?IndicatorValue $value, string $label): array
    {
        $reported = $value !== null && ($value->value_numeric !== null || filled($value->value_text));

        return [
            'label' => $label,
            'unit' => $indicator->unitLabel(),
            'reported' => $reported,
            // "Not reported", never a blank and never a zero. The same rule
            // the comparator holds, for the same reason.
            'display' => $reported
                ? ($value->value_numeric !== null
                    ? rtrim(rtrim(number_format((float) $value->value_numeric, 2), '0'), '.')
                    : (string) $value->value_text)
                : __('comparator.not_reported'),
            'numeric' => $reported && $value->value_numeric !== null ? (float) $value->value_numeric : null,
            'period' => $reported ? $value->period : null,
            'source' => $reported ? ($value->source_note ?: $indicator->source_standard) : null,
            'sample' => $reported && $value->is_sample,
        ];
    }

    /** @return array<string, mixed> */
    private function empty(Report $report): array
    {
        return [
            'subject' => $report->subject_type,
            'title' => $report->title,
            'subtitle' => '',
            'rows' => collect(),
            'sample' => false,
            'reported' => 0,
            'total' => 0,
            'period' => $report->period,
        ];
    }

    /**
     * The chart, as a proportional bar per row.
     *
     * The same treatment as the comparator: every bar states its figure
     * beside it, so the chart reads without colour and prints. A canvas
     * would need a table equivalent; this is already one.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{min: float, max: float}
     */
    public function range(Collection $rows): array
    {
        $numbers = $rows->pluck('numeric')->filter(fn ($n) => $n !== null)->values();

        return $numbers->isEmpty()
            ? ['min' => 0.0, 'max' => 1.0]
            : ['min' => (float) $numbers->min(), 'max' => (float) $numbers->max()];
    }
}

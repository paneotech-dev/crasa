<?php

namespace App\Support;

use App\Models\Country;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use Illuminate\Support\Collection;

/**
 * What the region map is showing, resolved from the query string.
 *
 * RegionMap.dc.html carries two modes and this class is the server side of
 * both. In "select" mode the fourteen shapes are a navigation surface. In
 * "choropleth" mode they shade by one indicator:
 *
 *     const SHADES = ["#E8F4EC", "#9CCFB2", "#4FA97B", "#0E5536"];
 *     const MISSING_FILL = "repeating-linear-gradient(135deg,#E5E0D5 0 4px,#CFC8B8 4px 8px)";
 *
 * Everything here is resolved before a byte of HTML is written. The map is
 * a server-rendered document that a script can make quicker to drive; it is
 * never a script that draws a map. A page that needs JavaScript to show
 * fourteen member states is worse than a static one.
 *
 * Two datasets, because they answer different questions at a plenary:
 *
 *  - any indicator that member states have actually filed figures for, and
 *  - data completeness, which is the only view that shows the Secretariat
 *    who has not filed. Three pale states on a projector settle an agenda
 *    item that an email thread does not.
 */
final class MapView
{
    /** The completeness dataset is selected by this reserved slug. */
    public const COMPLETENESS = 'completeness';

    /** Only these can be banded. A choropleth of free text means nothing. */
    private const SHADEABLE_TYPES = ['numeric', 'currency', 'percentage'];

    /**
     * @param  Collection<string, Country>  $countries  keyed by ISO code
     * @param  array<string, MapEntry>  $entries  keyed by ISO code
     * @param  array<int, array{label: string, options: array<int, array{value: string, label: string}>}>  $groups
     */
    private function __construct(
        public readonly Collection $countries,
        public readonly array $entries,
        public readonly array $groups,
        public readonly string $selection,
        public readonly ?Indicator $indicator,
        public readonly ?string $period,
    ) {}

    /**
     * @param  Collection<int, Country>  $countries
     */
    public static function resolve(Collection $countries, ?string $selection): self
    {
        // Alphabetical by name, which is the order the map is keyboard
        // reachable in and the order the list below it reads in. See the
        // component for why that order and not a geographic one.
        $countries = $countries->sortBy('name')->keyBy('iso_code');

        $groups = self::groups();
        $selection = self::valid($selection, $groups) ? (string) $selection : '';

        if ($selection === self::COMPLETENESS) {
            return new self($countries, self::completenessEntries($countries), $groups, $selection, null, null);
        }

        if ($selection === '') {
            return new self($countries, self::plainEntries($countries), $groups, '', null, null);
        }

        $indicator = Indicator::query()->where('slug', $selection)->first();

        if (! $indicator) {
            return new self($countries, self::plainEntries($countries), $groups, '', null, null);
        }

        [$entries, $period] = self::indicatorEntries($countries, $indicator);

        return new self($countries, $entries, $groups, $selection, $indicator, $period);
    }

    /** Whether the map is shading by a dataset or simply showing membership. */
    public function isShaded(): bool
    {
        return $this->selection !== '';
    }

    public function isCompleteness(): bool
    {
        return $this->selection === self::COMPLETENESS;
    }

    public function entry(string $iso): ?MapEntry
    {
        return $this->entries[$iso] ?? null;
    }

    /** The dataset's name, for the caption, the legend and the table. */
    public function title(): ?string
    {
        if ($this->isCompleteness()) {
            return __('members.map_completeness');
        }

        return $this->indicator?->name;
    }

    /**
     * The stated units. A choropleth without them is a picture of nothing:
     * a reader cannot tell 4.2 subscriptions from 4.2 million of them.
     */
    public function unit(): ?string
    {
        if ($this->isCompleteness()) {
            return __('members.map_unit_completeness');
        }

        $unit = $this->indicator?->unitLabel() ?: null;

        // Several indicator names in the register already end in their own
        // unit — "Mobile-cellular telephone subscriptions per 100
        // inhabitants". Printing it twice under the map reads as a mistake
        // rather than as precision.
        if ($unit && $this->indicator && str_contains(mb_strtolower($this->indicator->name), mb_strtolower($unit))) {
            return null;
        }

        return $unit;
    }

    /** How many member states carry a figure in the current dataset. */
    public function reportedCount(): int
    {
        return count(array_filter($this->entries, fn (MapEntry $e) => $e->reported));
    }

    public function missingCount(): int
    {
        return count($this->entries) - $this->reportedCount();
    }

    /**
     * What changed, in a sentence, for the reader who did not see it.
     *
     * The map answers instantly when a script is running, and an answer
     * that only arrives as colour is no answer at all to someone using a
     * screen reader. This is the text that is announced.
     */
    public function summary(): string
    {
        if (! $this->isShaded()) {
            return __('members.map_status_plain', ['count' => count($this->entries)]);
        }

        return __('members.map_status_shaded', [
            'dataset' => (string) $this->title(),
            'reported' => $this->reportedCount(),
            'total' => count($this->entries),
            'period' => $this->period ?: __('members.map_period_unknown'),
        ]);
    }

    /**
     * The dropdown, grouped. Completeness first because it is the view the
     * Secretariat opens the page for; the filed indicators follow, by
     * category, in the order the indicator register lists them.
     *
     * @return array<int, array{label: string, options: array<int, array{value: string, label: string}>}>
     */
    private static function groups(): array
    {
        $groups = [[
            'label' => __('members.map_group_overview'),
            'options' => [['value' => self::COMPLETENESS, 'label' => __('members.map_completeness')]],
        ]];

        // Only indicators a member state has actually filed a figure for.
        // Offering one that would draw fourteen hatched states wastes a
        // reader's click and makes the platform look emptier than it is.
        $indicators = Indicator::query()
            ->active()
            ->whereIn('data_type', self::SHADEABLE_TYPES)
            ->whereHas('values', fn ($q) => $q->whereNotNull('value_numeric'))
            ->orderBy('category')
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        foreach ($indicators->groupBy('category') as $category => $rows) {
            $groups[] = [
                'label' => __('indicators.category_'.$category),
                'options' => $rows->map(fn (Indicator $i) => [
                    'value' => $i->slug,
                    'label' => $i->unitLabel() && ! str_contains(mb_strtolower($i->name), mb_strtolower($i->unitLabel()))
                        ? $i->name.' ('.$i->unitLabel().')'
                        : $i->name,
                ])->values()->all(),
            ];
        }

        return $groups;
    }

    /** @param array<int, array{label: string, options: array<int, array{value: string, label: string}>}> $groups */
    private static function valid(?string $selection, array $groups): bool
    {
        if ($selection === null || $selection === '') {
            return false;
        }

        foreach ($groups as $group) {
            foreach ($group['options'] as $option) {
                if ($option['value'] === $selection) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Membership only. Every entry carries the regulator's acronym, which is
     * what the screen puts under the ISO code outside choropleth mode:
     * sub: m.acr.
     *
     * @param  Collection<string, Country>  $countries
     * @return array<string, MapEntry>
     */
    private static function plainEntries(Collection $countries): array
    {
        $entries = [];

        foreach ($countries as $iso => $country) {
            $entries[$iso] = new MapEntry(
                country: $country,
                display: $country->authority?->acronym ?? '',
                band: null,
                reported: true,
            );
        }

        return $entries;
    }

    /**
     * Completeness. Every member state has a figure here even when that
     * figure is zero: "has filed nothing" is a measurement, not a gap, so
     * nothing is hatched in this view and the palest states are the answer
     * the Secretariat came for.
     *
     * The scale is fixed 0 to 100 rather than stretched between the best and
     * worst state. Completeness has an absolute meaning and a relative scale
     * would flatter a region where nobody had filed.
     *
     * @param  Collection<string, Country>  $countries
     * @return array<string, MapEntry>
     */
    private static function completenessEntries(Collection $countries): array
    {
        $matrix = CompletenessMatrix::build();
        $entries = [];

        foreach ($countries as $iso => $country) {
            $percent = (int) ($matrix['totals'][$country->id]['percent'] ?? 0);

            $entries[$iso] = new MapEntry(
                country: $country,
                display: $percent.'%',
                band: match (true) {
                    $percent >= 75 => 3,
                    $percent >= 50 => 2,
                    $percent >= 25 => 1,
                    default => 0,
                },
                reported: true,
                numeric: (float) $percent,
            );
        }

        return $entries;
    }

    /**
     * One indicator, one period, fourteen states.
     *
     * The whole map reads a single period, because a colour scale that put
     * Angola's 2019 figure next to Zambia's 2025 figure would be comparing
     * two different questions and calling the difference a regional
     * pattern.
     *
     * Which period: the one the most member states have filed, and the most
     * recent of those if two are level. Not simply the latest, because the
     * latest period is usually the one two or three states have got to
     * first, and a map drawn on it shows eleven hatched states and reports
     * a filing problem that does not exist.
     *
     * @param  Collection<string, Country>  $countries
     * @return array{array<string, MapEntry>, string|null}
     */
    private static function indicatorEntries(Collection $countries, Indicator $indicator): array
    {
        $period = IndicatorValue::query()
            ->where('indicator_id', $indicator->id)
            ->whereNotNull('value_numeric')
            ->selectRaw('period, COUNT(DISTINCT country_id) as filed')
            ->groupBy('period')
            ->orderByDesc('filed')
            ->orderByDesc('period')
            ->value('period');

        $values = IndicatorValue::query()
            ->where('indicator_id', $indicator->id)
            ->when($period, fn ($q) => $q->where('period', $period))
            ->get()
            ->keyBy('country_id');

        $numbers = $values
            ->filter(fn (IndicatorValue $v) => $v->value_numeric !== null)
            ->map(fn (IndicatorValue $v) => (float) $v->value_numeric);

        $min = $numbers->min() ?? 0.0;
        $max = $numbers->max() ?? 0.0;

        $entries = [];

        foreach ($countries as $iso => $country) {
            $cell = new ComparisonCell($indicator, $country, $values[$country->id] ?? null);

            $entries[$iso] = new MapEntry(
                country: $country,
                display: $cell->display(),
                band: $cell->band($min, $max),
                reported: $cell->isReported(),
                numeric: $cell->isReported() && $values[$country->id]?->value_numeric !== null
                    ? (float) $values[$country->id]->value_numeric
                    : null,
            );
        }

        return [$entries, $period ? (string) $period : null];
    }
}

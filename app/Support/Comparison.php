<?php

namespace App\Support;

use App\Models\Country;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * One comparison, resolved from its URL.
 *
 * RP-10 asks for at least two member states against at least one indicator.
 * Screen 03 draws two modes rather than one:
 *
 *   Browse one indicator     all fourteen member states, one indicator
 *   Compare member states    up to four member states, a whole category
 *
 * A comparison is entirely described by its query string, so a regulator can
 * paste the URL into an email and the recipient sees the same figures. That
 * is also what makes the server-rendered fallback and the exports possible:
 * every one of them reads this class, not the React component.
 */
final class Comparison
{
    public const BROWSE = 'browse';

    public const COMPARE = 'compare';

    /** Screen 03: "compare up to four member states". */
    public const MAX_COUNTRIES = 4;

    /** What the comparator opens on when the URL names no member state. */
    public const DEFAULT_COUNTRIES = ['bw', 'mz'];

    /**
     * @param  Collection<int, Country>  $countries
     * @param  Collection<int, Indicator>  $indicators
     */
    private function __construct(
        public readonly string $mode,
        public readonly Collection $countries,
        public readonly Collection $indicators,
        public readonly ?string $category,
        public readonly ?Indicator $indicator,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     */
    public static function fromQuery(array $query): self
    {
        $mode = ($query['mode'] ?? self::COMPARE) === self::BROWSE ? self::BROWSE : self::COMPARE;

        /*
         * Both shapes of the same selection.
         *
         * The canonical form is ?countries=bw,mz, which is what the page
         * writes and what a reader pastes into an email. ?countries[]=bw is
         * what a form of checkboxes sends and what the rest of the platform
         * now uses for a multi-select facet, and it reached here as an
         * array being cast to a string: "Array to string conversion", and a
         * 500 on a public page from a hand-typed URL.
         */
        $raw = $query['countries'] ?? '';
        $raw = is_array($raw) ? $raw : explode(',', (string) $raw);

        $codes = collect($raw)
            ->filter(fn ($code) => is_scalar($code))
            ->map(fn ($code) => strtolower(trim((string) $code)))
            ->filter()
            ->unique()
            ->take(self::MAX_COUNTRIES);

        $countries = $codes->isEmpty()
            ? collect()
            : Country::query()
                ->whereIn('iso_code', $codes->all())
                ->orderBy('name')
                ->get();

        if ($mode === self::BROWSE) {
            // Browse is the whole region against one indicator, so the
            // country selection does not apply and is not silently kept.
            $indicator = Indicator::query()
                ->where('slug', (string) ($query['indicator'] ?? ''))
                ->first()
                ?? Indicator::query()->orderBy('display_order')->first();

            return new self(
                self::BROWSE,
                Country::query()->orderBy('name')->get(),
                collect($indicator ? [$indicator] : []),
                $indicator?->category,
                $indicator,
            );
        }

        /*
         * Day 2 (29 October 2026): compare mode is one indicator across up
         * to four member states, chosen in three steps, and it opens on a
         * result rather than on an empty panel. With no indicator named,
         * the first indicator of a named category (the older URLs), then
         * the first indicator overall. With no countries key at all,
         * Botswana and Mozambique; an explicit empty list is still empty.
         */
        $active = Indicator::query()->where('is_active', true)->orderBy('display_order');
        $indicator = Indicator::query()->where('is_active', true)->where('slug', (string) ($query['indicator'] ?? ''))->first()
            ?? (clone $active)->where('category', (string) ($query['category'] ?? ''))->first()
            ?? (clone $active)->first();

        if (! array_key_exists('countries', $query)) {
            $countries = Country::query()->whereIn('iso_code', self::DEFAULT_COUNTRIES)->orderBy('name')->get();
        }

        $category = $indicator?->category;
        $indicators = collect($indicator ? [$indicator] : []);

        return new self(self::COMPARE, $countries, $indicators, $category, $indicator);
    }

    /** The canonical query string. What a regulator pastes into an email. */
    public function queryParameters(): array
    {
        return $this->mode === self::BROWSE
            ? ['mode' => self::BROWSE, 'indicator' => $this->indicator?->slug]
            : [
                'mode' => self::COMPARE,
                'indicator' => $this->indicator?->slug,
                'countries' => $this->countries->pluck('iso_code')->implode(','),
            ];
    }

    public function permalink(): string
    {
        return route('comparator', array_filter(
            ['locale' => app()->getLocale()] + $this->queryParameters()
        ));
    }

    public function isEmpty(): bool
    {
        return $this->mode === self::COMPARE && $this->countries->isEmpty();
    }

    /**
     * Every cell of the comparison, indicator by country.
     *
     * Loaded in one query. A cell is never absent from this structure: a
     * country with no value for an indicator gets a cell that says so.
     * Screen 03: "A blank cell would be indistinguishable from a value of
     * zero, so no cell is ever left blank."
     *
     * @return array<int, array<int, ComparisonCell>>
     */
    public function cells(): array
    {
        if ($this->indicators->isEmpty() || $this->countries->isEmpty()) {
            return [];
        }

        /*
         * No country-scope bypass, because there is nothing to bypass.
         * indicator_values holds the published regional dataset and carries
         * no CountryScope: it is what the completeness matrix and this
         * comparator both read, and both are public pages showing all
         * fourteen member states.
         *
         * A member state's unvalidated return is a different thing living in
         * a different table, and it stays there until the Secretariat
         * validates it. That is what keeps this read simple and safe.
         */
        $values = IndicatorValue::query()
            ->whereIn('indicator_id', $this->indicators->pluck('id'))
            ->whereIn('country_id', $this->countries->pluck('id'))
            ->orderBy('period')
            ->get()
            ->groupBy(fn (IndicatorValue $value) => $value->indicator_id.':'.$value->country_id)
            // The most recent period for each pair. A comparison of figures
            // from different years is a comparison of nothing, so the period
            // travels with the value and is shown.
            ->map(fn (Collection $group) => $group->last());

        $cells = [];

        foreach ($this->indicators as $indicator) {
            foreach ($this->countries as $country) {
                $cells[$indicator->id][$country->id] = new ComparisonCell(
                    $indicator,
                    $country,
                    $values->get($indicator->id.':'.$country->id),
                );
            }
        }

        return $cells;
    }

    /**
     * The cells of the one indicator in reading order: in compare mode the
     * chosen member states by name, in browse mode a ranking from the
     * highest figure down, with "Not reported" always last because it is
     * not a low value.
     *
     * @return Collection<int, ComparisonCell>
     */
    public function ranked(): Collection
    {
        $row = collect($this->cells()[$this->indicator?->id] ?? []);

        if ($this->mode !== self::BROWSE) {
            return $row->values();
        }

        return $row->sort(function (ComparisonCell $a, ComparisonCell $b) {
            $av = $a->isReported() && $a->value->value_numeric !== null;
            $bv = $b->isReported() && $b->value->value_numeric !== null;

            if ($av !== $bv) {
                return $av ? -1 : 1;
            }

            return $av
                ? ((float) $b->value->value_numeric <=> (float) $a->value->value_numeric)
                : strcmp($a->country->name, $b->country->name);
        })->values();
    }

    /** The highest reported figure, the scale of the bar chart. */
    public function scaleMax(): float
    {
        $max = $this->ranked()
            ->filter(fn (ComparisonCell $c) => $c->isReported() && $c->value->value_numeric !== null)
            ->map(fn (ComparisonCell $c) => (float) $c->value->value_numeric)
            ->max();

        return $max && $max > 0 ? (float) $max : 1.0;
    }

    /**
     * The chart in one or two sentences, for the status region and for the
     * chart's text alternative.
     */
    public function summary(): string
    {
        if ($this->indicator === null || $this->countries->isEmpty()) {
            return __('d.summary_empty');
        }

        $cells = $this->ranked();
        $reported = $cells->filter(fn (ComparisonCell $c) => $c->isReported() && $c->value->value_numeric !== null);

        $parts = $cells->take($this->mode === self::BROWSE ? 3 : self::MAX_COUNTRIES)
            ->map(fn (ComparisonCell $c) => $c->isReported()
                ? __('d.summary_part', ['state' => $c->country->name, 'value' => $c->display(), 'period' => $c->period()])
                : __('d.summary_missing', ['state' => $c->country->name]))
            ->implode('; ');

        $lead = $this->mode === self::BROWSE
            ? __('d.summary_browse', ['indicator' => $this->indicator->name, 'reported' => $reported->count(), 'total' => $cells->count()])
            : __('d.summary_compare', ['indicator' => $this->indicator->name]);

        return $lead.' '.$parts.'.';
    }

    /** How many of the cells actually carry a figure. */
    public function reportedCount(): int
    {
        $reported = 0;

        foreach ($this->cells() as $row) {
            foreach ($row as $cell) {
                $reported += $cell->isReported() ? 1 : 0;
            }
        }

        return $reported;
    }

    public function latestUpdate(): ?Carbon
    {
        // max() comes back as the raw column string, not a Carbon, because
        // no cast applies to an aggregate.
        $latest = IndicatorValue::query()
            ->whereIn('indicator_id', $this->indicators->pluck('id'))
            ->max('updated_at');

        return $latest ? Carbon::parse($latest) : null;
    }

    /**
     * Whether any figure in this comparison is sample data.
     *
     * Screen 03 keeps the demonstration-data notice "on screen and in every
     * export, until each value is confirmed by its national regulator". So
     * the notice is driven by the flag on the rows rather than by a
     * constant somebody has to remember to remove.
     */
    public function carriesSampleData(): bool
    {
        return IndicatorValue::query()
            ->whereIn('indicator_id', $this->indicators->pluck('id'))
            ->whereIn('country_id', $this->countries->pluck('id'))
            ->where('is_sample', true)
            ->exists();
    }
}

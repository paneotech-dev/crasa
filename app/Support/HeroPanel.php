<?php

namespace App\Support;

use App\Models\Country;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\Publication;
use App\Models\Sector;

/**
 * The figures in the hero's data panel.
 *
 * Screen 02 draws two kinds of slide. One carries a photograph behind a
 * scrim, and its copy runs the full width. The other carries no photograph
 * and fills the second column with a panel:
 *
 *   <div style="min-width:0;background:var(--c-navy-mid);border:1px solid #2C3A55;
 *               border-radius:var(--r-md);padding:var(--s-6)">
 *
 * The homepage runs without a photograph, so it is the second kind of slide,
 * and the panel is what the screen puts there. Leaving it out was leaving
 * out the thing the screen used to stop 640px of flat navy reading as an
 * empty container.
 *
 * WHAT IT SHOWS, AND WHAT IT DELIBERATELY DOES NOT
 *
 * Three bars, one per sector, each the share of the indicator register that
 * member states have filed for that sector across the whole region.
 *
 * Not per-member-state completeness, which is the obvious thing to put in a
 * bar chart and the wrong thing to put on a public homepage. That view
 * ranks fourteen sovereign regulators by how well they have filed, and it
 * lives behind an account on the completeness dashboard for that reason.
 * Publishing it on the front page is CRASA's decision to take, not ours.
 *
 * Regional totals by sector say something true, useful and only true of
 * CRASA: three sectors, fourteen states, one register.
 *
 * Every figure is computed. Screen 08's own band carries "350M+ citizens
 * served", which is a number nobody has sourced for us, and it is not here.
 */
final class HeroPanel
{
    /**
     * @return array{
     *   bars: array<int, array{name: string, value: string, percent: int, filed: int, expected: int}>,
     *   counts: array<int, array{value: string, label: string, url: string|null}>,
     *   sample: bool
     * }
     */
    public static function build(): array
    {
        $countries = Country::count();
        $locale = app()->getLocale();

        $bars = [];

        foreach (Sector::orderBy('id')->get() as $sector) {
            $indicators = Indicator::active()->where('sector_id', $sector->id)->count();
            $expected = $indicators * $countries;

            if ($expected === 0) {
                continue;
            }

            $filed = IndicatorValue::query()
                ->whereHas('indicator', fn ($q) => $q->where('sector_id', $sector->id)->where('is_active', true))
                ->count();

            $bars[] = [
                'name' => $sector->name,
                // The figure as text beside the bar, because a bar a reader
                // cannot read a number off is a picture of a proportion.
                'value' => round($filed / $expected * 100).'%',
                'percent' => (int) min(100, round($filed / $expected * 100)),
                'filed' => $filed,
                'expected' => $expected,
            ];
        }

        return [
            'bars' => $bars,
            'counts' => self::counts($countries, $locale),
            // The demonstration notice stays until every figure behind these
            // bars is confirmed by its national regulator, exactly as it does
            // on the comparator and in every export.
            'sample' => IndicatorValue::where('is_sample', true)->exists(),
        ];
    }

    /**
     * Slide two: one indicator, two member states, side by side.
     *
     * The comparator is the platform's reason to exist and it sat four
     * sections down the page. This is the same query the comparator runs,
     * narrowed to the two states with the widest gap on the most-reported
     * indicator, because a comparison of two near-identical figures
     * demonstrates nothing.
     *
     * Returns null rather than a placeholder when the register cannot
     * support a comparison. A hero slide that says "no data" is worse than
     * a hero with two slides.
     *
     * @return array{indicator: string, unit: string|null, period: string|null, url: string, sample: bool, rows: array<int, array{country: string, code: string, value: string}>}|null
     */
    public static function comparison(): ?array
    {
        $indicator = Indicator::active()
            ->withCount('values')
            ->orderByDesc('values_count')
            ->first();

        if ($indicator === null || $indicator->values_count < 2) {
            return null;
        }

        $values = IndicatorValue::with('country')
            ->where('indicator_id', $indicator->id)
            ->whereNotNull('value_numeric')
            ->orderByDesc('value_numeric')
            ->get();

        if ($values->count() < 2) {
            return null;
        }

        // The widest gap the register can show, which is the pair that makes
        // the case for a comparator at all.
        $rows = collect([$values->first(), $values->last()])
            ->filter(fn ($value) => $value?->country !== null)
            ->map(fn ($value) => [
                'country' => $value->country->name,
                'code' => strtoupper($value->country->iso_code),
                // The comparator's own formatter, so a percentage
                // in the hero and the same percentage on screen 03 are
                // written identically.
                'value' => (new ComparisonCell($indicator, $value->country, $value))->display(),
            ])
            ->values()
            ->all();

        if (count($rows) < 2) {
            return null;
        }

        return [
            'indicator' => $indicator->name,
            'unit' => $indicator->unitLabel(),
            'period' => $values->first()->period,
            'url' => route('comparator', ['locale' => app()->getLocale()]),
            'sample' => $values->contains('is_sample', true),
            'rows' => $rows,
        ];
    }

    /**
     * Slide three: the most recent publication, as itself.
     *
     * Not a card lifted out of the library strip further down the page. The
     * hero states the one thing a regulator arriving at the site wants to
     * know first, which is what CRASA published most recently and where the
     * file is.
     *
     * @return array{title: string, kind: string, year: string|null, format: string|null, size: string|null, sample: bool, url: string}|null
     */
    public static function publication(): ?array
    {
        $publication = Publication::listed()
            ->with('translations')
            ->orderByDesc('published_on')
            ->first();

        if ($publication === null) {
            return null;
        }

        $title = $publication->translated('title', '');

        if ($title === '') {
            return null;
        }

        $document = $publication->primaryDocument();

        return [
            'title' => $title,
            'kind' => __($publication->publication_type->labelKey()),
            'year' => $publication->published_on?->format('Y'),
            'format' => $document
                ? strtoupper(pathinfo($document->original_filename, PATHINFO_EXTENSION))
                : null,
            'size' => $document?->readableSize(),
            'sample' => (bool) $publication->is_sample,
            'url' => route('publications.show', [
                'locale' => app()->getLocale(),
                'slug' => $publication->slug,
            ]),
        ];
    }

    /**
     * The counts beneath the bars. Each goes where it points: a figure a
     * reader cannot follow is a fact about the site rather than a way in.
     *
     * @return array<int, array{value: string, label: string, url: string|null}>
     */
    private static function counts(int $countries, string $locale): array
    {
        return array_values(array_filter([
            [
                'value' => (string) $countries,
                'label' => __('blocks.hero_count_members'),
                'url' => route('members', ['locale' => $locale]),
            ],
            [
                'value' => (string) Indicator::active()->count(),
                'label' => __('blocks.hero_count_indicators'),
                'url' => route('comparator', ['locale' => $locale]),
            ],
            [
                'value' => (string) Publication::listed()->count(),
                'label' => __('blocks.hero_count_publications'),
                'url' => route('publications', ['locale' => $locale]),
            ],
        ], fn (array $count) => $count['value'] !== '0'));
    }
}

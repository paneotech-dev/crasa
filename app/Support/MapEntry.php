<?php

namespace App\Support;

use App\Models\Country;

/**
 * One member state's answer on the map.
 *
 * The map states every figure as text as well as a shade. RegionMap.dc.html
 * puts the value under the ISO code on the map itself:
 *
 *     sub: choropleth ? (v ? v.display : "Not reported") : m.acr
 *
 * and again in the accessible name:
 *
 *     aria: choropleth ? m.country + ", " + (v ? v.display : "not reported")
 *
 * That is not a nicety. Colour cannot be the only carrier of meaning under
 * SC 1.4.1, the platform prints in mono, and a plenary projector will wash
 * out two of the four steps. The shade is the pattern a reader sees across
 * fourteen states at a glance; the text is the figure they quote.
 */
final class MapEntry
{
    public function __construct(
        public readonly Country $country,
        public readonly string $display,
        public readonly ?int $band,
        public readonly bool $reported,
        public readonly ?float $numeric = null,
        /**
         * The most recent period this member state has filed anything for.
         *
         * A shaded map with no freshness signal invites a reader to treat a
         * 2019 return as current. The completeness dashboard has carried
         * this since section 19 and the map had no equivalent, so the same
         * figure now reaches both.
         */
        public readonly ?string $latestPeriod = null,
    ) {}

    public function iso(): string
    {
        return $this->country->iso_code;
    }

    /**
     * The band as an attribute value. "none" rather than an empty string,
     * because a missing figure is a state of its own and gets the hatch,
     * not the lightest step. "Not reported" and "lowest value" are different
     * claims about a member state and must not look similar.
     */
    /** current, ageing, stale or none, on the same scale the grid uses. */
    public function freshness(): string
    {
        return CompletenessMatrix::freshnessOf($this->latestPeriod);
    }

    public function bandAttribute(): string
    {
        return $this->band === null ? 'none' : (string) $this->band;
    }
}

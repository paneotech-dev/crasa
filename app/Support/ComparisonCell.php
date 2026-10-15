<?php

namespace App\Support;

use App\Models\Country;
use App\Models\Indicator;
use App\Models\IndicatorValue;

/**
 * One country's answer for one indicator, or the stated absence of one.
 *
 * Screen 03 is explicit about the absence: "Where a member state has not
 * reported an indicator, the cell reads Not reported with a dashed marker,
 * and the row keeps its height. A blank cell would be indistinguishable
 * from a value of zero, so no cell is ever left blank."
 *
 * "Not reported" and "0" mean opposite things. A regulator reading a zero
 * for postal offices concludes something false about a member state; a
 * regulator reading "Not reported" concludes something true about the data.
 */
final class ComparisonCell
{
    public function __construct(
        public readonly Indicator $indicator,
        public readonly Country $country,
        public readonly ?IndicatorValue $value,
    ) {}

    public function isReported(): bool
    {
        return $this->value !== null
            && ($this->value->value_numeric !== null || filled($this->value->value_text));
    }

    /**
     * The figure as a reader sees it.
     *
     * Screen 03 renders each type distinctly: a number with tabular figures
     * and thousands separators, a percentage with its sign, a currency with
     * its code in front, a boolean as Yes or No, a year bare, a list as
     * comma-separated text.
     */
    public function display(): string
    {
        if (! $this->isReported()) {
            return __('comparator.not_reported');
        }

        if ($this->value->value_numeric === null) {
            return (string) $this->value->value_text;
        }

        $number = (float) $this->value->value_numeric;

        return match ($this->indicator->data_type) {
            'percentage' => $this->number($number).'%',
            'boolean' => __($number > 0 ? 'comparator.yes' : 'comparator.no'),
            'year' => (string) (int) $number,
            'currency' => trim(($this->value->currency ?? '').' '.$this->number($number, 2)),
            default => $this->number($number),
        };
    }

    /**
     * The provenance. RP-10 and stage G both exist for this: a regulatory
     * figure without a source is worthless in a ministerial brief, and the
     * first question anyone asks of a comparison is where the number came
     * from.
     */
    public function source(): ?string
    {
        if (! $this->isReported()) {
            return null;
        }

        return $this->value->source_note
            ?: ($this->indicator->source_standard ?: null);
    }

    public function period(): ?string
    {
        return $this->isReported() ? $this->value->period : null;
    }

    /**
     * The provenance badge. Four states, and each one is a different fact
     * about how much weight the figure carries.
     */
    public function provenance(): string
    {
        if (! $this->isReported()) {
            return 'not_reported';
        }

        // A sample figure keeps its is_sample flag for content:strip-samples,
        // but is not badged to readers (owner's instruction, 15 October 2026).
        return $this->value->confidence === 'validated' ? 'validated' : 'reported';
    }

    /** Where this figure sits between the lowest and highest in its row. */
    public function share(float $min, float $max): ?float
    {
        if (! $this->isReported() || $this->value->value_numeric === null) {
            return null;
        }

        if ($max <= $min) {
            return 1.0;
        }

        return max(0.0, min(1.0, ((float) $this->value->value_numeric - $min) / ($max - $min)));
    }

    /** The choropleth band, 0 to 3, or null for not reported. */
    public function band(float $min, float $max): ?int
    {
        $share = $this->share($min, $max);

        return $share === null ? null : min(3, (int) floor($share * 4));
    }

    private function number(float $value, int $decimals = 1): string
    {
        // Whole numbers keep no decimal point: 1,143 rather than 1,143.0.
        return $value == (int) $value && $decimals === 1
            ? number_format($value)
            : number_format($value, $decimals);
    }
}

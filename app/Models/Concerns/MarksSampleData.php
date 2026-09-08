<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Demonstration data carries a flag so one admin action can remove all of it
 * before go-live. Nothing unverified is ever published unmarked.
 */
trait MarksSampleData
{
    /** Rows the Secretariat has not confirmed. */
    public function scopeSample(Builder $query): Builder
    {
        return $query->where('is_sample', true);
    }

    /** Rows safe to cite. Use this on every public-facing query. */
    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('is_sample', false);
    }
}

<?php

namespace App\Search;

use Illuminate\Support\Collection;

/**
 * What a driver returns. Deliberately plain: no driver-specific shape leaks
 * upward, so the views cannot come to depend on one engine's response.
 */
final class SearchResults
{
    /**
     * @param  Collection<int, SearchHit>  $hits
     * @param  array<string, array<string, int>>  $facets
     */
    public function __construct(
        public readonly Collection $hits,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
        public readonly array $facets = [],
    ) {}

    public static function empty(): self
    {
        return new self(collect(), 0, 1, 12);
    }

    public function isEmpty(): bool
    {
        return $this->total === 0;
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / max(1, $this->perPage)));
    }
}

<?php

namespace App\Search;

/**
 * One result. Shaped for screen 07's document row, which is what the results
 * list reuses rather than inventing a pattern for a screen that does not
 * exist.
 */
final class SearchHit
{
    public function __construct(
        public readonly string $type,
        public readonly string $title,
        public readonly string $snippet,
        public readonly string $url,
        public readonly ?string $meta = null,
        public readonly ?string $country = null,
        public readonly ?string $sector = null,
        public readonly ?int $year = null,
        public readonly float $score = 0.0,
    ) {}
}

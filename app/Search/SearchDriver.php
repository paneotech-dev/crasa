<?php

namespace App\Search;

/**
 * The seam a managed ElasticSearch service swaps in behind.
 *
 * ElasticSearch cannot run on this host: no JVM, and this is shared cPanel
 * hosting. MariaDB InnoDB FULLTEXT is what is available and it is what
 * ships. That is a disclosed deviation, recorded in OPEN_ITEMS.
 *
 * Everything above this interface — the controller, the facets, the result
 * rows, the highlighting — is driver-agnostic, so moving to a managed
 * service later is a new class and a config line rather than a rewrite.
 */
interface SearchDriver
{
    /**
     * @param  array<string, mixed>  $filters  country, sector, type, year
     */
    public function search(string $query, array $filters = [], int $perPage = 12, int $page = 1): SearchResults;

    /**
     * Predictive suggestions for a partial query.
     *
     * @return list<string>
     */
    public function suggest(string $partial, int $limit = 6): array;

    public function name(): string;
}

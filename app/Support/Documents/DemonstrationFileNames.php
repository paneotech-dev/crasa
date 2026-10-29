<?php

namespace App\Support\Documents;

/**
 * The names the demonstration folders show for their papers.
 *
 * The seeder files its papers under stable paths ("sample-validated-returns
 * .pdf"), and until 29 October 2026 the folder page showed that path as the
 * file name, so the room read "sample-" in front of every paper. The path
 * stays (the seeder finds its own files by it); the name a reader sees is
 * the one a regulator would give the paper.
 */
final class DemonstrationFileNames
{
    /** The seeder slugs this class names. */
    public const SLUGS = [
        'sample-validated-returns', 'sample-market-review-summary',
        'sample-annual-return-guidance', 'sample-return-checklist',
        'sample-committee-working-paper', 'sample-committee-agenda',
        'sample-secretariat-note-workshop', 'sample-secretariat-note-platform',
    ];

    /**
     * The file name for a seeder slug, or null for a slug this class does
     * not know (which is then left as it is).
     */
    public static function for(string $slug, ?string $authority, ?string $country, ?string $committee, int $year): ?string
    {
        $owner = $authority ?: ($country ?: 'Member state');
        $committee = $committee ?: 'Committee';

        $name = match ($slug) {
            'sample-validated-returns' => $owner.' validated annual returns',
            'sample-market-review-summary' => $owner.' market review summary',
            'sample-annual-return-guidance' => 'Annual return guidance '.$year,
            'sample-return-checklist' => 'Annual return checklist '.$year,
            'sample-committee-working-paper' => $committee.' working paper '.$year,
            'sample-committee-agenda' => $committee.' session agenda '.$year,
            'sample-secretariat-note-workshop' => 'Secretariat note on the regional workshop '.$year,
            'sample-secretariat-note-platform' => 'Secretariat note on the information-sharing platform '.$year,
            default => null,
        };

        return $name === null ? null : $name.'.pdf';
    }
}

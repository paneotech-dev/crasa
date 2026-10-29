<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\IndicatorSeeder;
use Database\Seeders\IndicatorValueSeeder;
use Database\Seeders\PublicationSeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The four affordance failures named in the last report, closed.
 *
 * Two directions to the same rule: anything that looks interactive must be
 * interactive, and anything that is not must not look it.
 */
class AffordanceFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(PublicPagesSeeder::class);
        $this->seed(PublicationSeeder::class);
        $this->seed(IndicatorSeeder::class);
        $this->seed(IndicatorValueSeeder::class);
    }

    /* 1. Publication rows ---------------------------------------------------- */

    /**
     * The whole row opens the publication. It used to be the title text
     * alone, which on a 64px band with a thumbnail beside it is a small
     * target surrounded by things that look like part of the same click.
     */
    public function test_the_whole_publication_row_opens_the_publication(): void
    {
        $css = File::get(public_path('css/blocks.css'));

        $this->assertStringContainsString(
            '.b-row__title a::after { content: ""; position: absolute; inset: 0; }',
            $css,
            'Only the title of a publication row is clickable.'
        );

        // The row has to establish a containing block, or the stretched
        // area covers the whole page.
        $this->assertMatchesRegularExpression('~\.b-row \{[^}]*position: relative~', $css);
    }

    /**
     * The download stays a separate destination and stays reachable: it is
     * raised above the stretched area, so a pointer over the button gets
     * the button and a pointer anywhere else gets the publication.
     */
    public function test_the_download_stays_a_distinct_action_above_the_row_link(): void
    {
        $css = File::get(public_path('css/blocks.css'));

        $this->assertMatchesRegularExpression(
            '~\.b-row__actions \{[^}]*position: relative[^}]*z-index: 1~',
            $css,
            'The download button sits under the row link and cannot be clicked.'
        );

        $this->get('/en/publications')->assertOk()->assertSee('documents/', false);
    }

    /**
     * Only the title carries the link, so a screen reader hears one link
     * named after the document rather than the whole row read out as one.
     */
    public function test_the_row_link_keeps_its_own_accessible_name(): void
    {
        $html = $this->get('/en/publications')->assertOk()->getContent();

        preg_match('~<div class="b-row__title">\s*<a[^>]*>\s*([^<]+)~', $html, $match);
        $this->assertNotEmpty($match, 'A publication row has no titled link.');
        $this->assertNotEmpty(trim($match[1]));
    }

    /* 2 and 3. Sortable columns ---------------------------------------------- */

    /**
     * A table heading in a data tool is a control everywhere else a
     * regulator works. One that looks like a heading and does nothing is
     * the affordance failure in its plainest form.
     */
    /**
     * Day 2 (D2): browse mode is a ranking of all fourteen, highest first,
     * in the table and the chart alike. The sort links are gone; the order
     * is the view, and its URL is the view's URL.
     */
    public function test_the_comparator_ranks_by_value(): void
    {
        $ranked = $this->values($this->get('/en/comparator?mode=browse')->assertOk()->getContent());

        $this->assertNotEmpty($ranked);
        $sorted = $ranked;
        rsort($sorted);
        $this->assertSame($sorted, $ranked, 'The ranking does not descend.');
    }

    /**
     * "Not reported" is not a low value. It sorts last whichever direction
     * is chosen, because ordering it as zero would rank a member state that
     * has filed nothing below one that filed a genuine zero.
     */
    public function test_not_reported_sorts_last_in_both_directions(): void
    {
        foreach (['asc', 'desc'] as $direction) {
            $html = $this->get('/en/comparator?mode=browse&sort=value&dir='.$direction)->assertOk()->getContent();

            preg_match_all('~<tr( data-missing)?>~', $html, $rows);
            $flags = array_map(fn ($m) => $m !== '', $rows[1]);

            // Once an unreported row appears, every row after it must be
            // unreported too.
            $seenMissing = false;
            foreach ($flags as $isMissing) {
                if ($isMissing) {
                    $seenMissing = true;

                    continue;
                }

                $this->assertFalse($seenMissing, 'A reported value sorted below a "Not reported" one, direction '.$direction.'.');
            }
        }
    }

    public function test_the_ranking_needs_no_script(): void
    {
        $html = $this->get('/en/comparator?mode=browse')->assertOk()->getContent();

        $this->assertStringContainsString('<th scope="col">'.__('d.col_rank').'</th>', $html);
    }

    public function test_the_completeness_grid_sorts_by_how_much_each_state_has_filed(): void
    {
        $user = User::factory()->create();

        $ascending = $this->states($this->actingAs($user)->get('/en/completeness?sort=total&dir=asc')->assertOk()->getContent());
        $descending = $this->states($this->actingAs($user)->get('/en/completeness?sort=total&dir=desc')->assertOk()->getContent());

        $this->assertNotEmpty($ascending);
        $this->assertSame(array_reverse($ascending), $descending);
        $this->assertNotSame($ascending, $descending, 'Sorting the completeness grid changed nothing.');
    }

    /**
     * A column with no order is not a control. Provenance has four values
     * and no ranking, and sorting by it would put "reported" above
     * "validated" for no reason a reader could name.
     */
    public function test_a_column_with_no_natural_order_is_not_dressed_as_sortable(): void
    {
        $html = $this->get('/en/comparator?mode=browse')->assertOk()->getContent();

        preg_match('~<th scope="col">([^<]*Status[^<]*)</th>~i', $html, $match);
        $this->assertNotEmpty($match, 'The provenance column is missing or has become a control.');
    }

    /* 4. Status pills ---------------------------------------------------------- */

    /**
     * A filter chip and a status pill were the same shape on the same page,
     * so readers were trying to click the statuses.
     *
     * CLAUDE.md already settles it: "--r-pill on status badges only" and
     * "--r-sm on buttons". The public stylesheet had them the wrong way
     * round, and the back office had it right since screen 09. Two roles,
     * two radii, and no colour changed so nothing regressed on contrast.
     */
    public function test_a_status_pill_and_a_filter_chip_are_not_the_same_shape(): void
    {
        $css = File::get(public_path('css/blocks.css'));

        $this->assertMatchesRegularExpression(
            '~\.c-chip \{[^}]*border-radius: var\(--r-sm\)~',
            $css,
            'A filter chip is still pill-shaped, which is the shape reserved for status badges.'
        );
        $this->assertMatchesRegularExpression(
            '~\.c-pill \{[^}]*border-radius: var\(--r-pill\)~',
            $css,
            'A status pill is not pill-shaped, so it reads as a control.'
        );
    }

    /** And it matches the back office, which has had it right all along. */
    public function test_the_public_and_back_office_status_badges_agree(): void
    {
        $this->assertMatchesRegularExpression(
            '~\.a-pill \{[^}]*border-radius: var\(--r-pill\)~',
            File::get(public_path('css/admin.css'))
        );
    }

    /** @return array<int, float> */
    private function values(string $html): array
    {
        preg_match_all('~class="c-cmp__num"[^>]*>([\d,.]+)~', $html, $matches);

        return array_map(fn ($v) => (float) str_replace(',', '', $v), $matches[1]);
    }

    /** @return array<int, string> */
    private function states(string $html): array
    {
        preg_match_all('~/en/members/([a-z]{2})"~', $html, $matches);

        return $matches[1];
    }
}

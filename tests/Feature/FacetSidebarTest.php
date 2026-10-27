<?php

namespace Tests\Feature;

use App\Models\Publication;
use App\Support\Locales;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\NewsroomSeeder;
use Database\Seeders\PublicationSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Tests\TestCase;

/**
 * The filter sidebar, screen 07 line 115.
 *
 * THE ONE PROMISE THIS FILE HOLDS. Every count beside every option is the
 * size of the result that clicking it would produce. Not how many exist in
 * total, and not how many match that option in isolation. A "South Africa
 * (7)" that yields two results once the year filter is applied is worse
 * than no number at all, and it is the failure a reader reports as "the
 * filters do not work".
 *
 * So every facet is walked, every option is checked, and the result count
 * on the page that comes back is asserted against the number that was
 * printed beside the box before the click.
 */
class FacetSidebarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(CommitteeSeeder::class);
        $this->seed(PublicationSeeder::class);
        $this->seed(NewsroomSeeder::class);
    }

    /**
     * Every checkbox on the page: field, value, whether it is disabled and
     * the number printed beside it.
     *
     * @return list<array{field: string, value: string, count: int, disabled: bool}>
     */
    /**
     * DOMDocument rather than DomCrawler, which is not installed and adding
     * a dependency is a decision rather than a commit. XPath answers every
     * question this file asks.
     */
    private function xpath(string $url): DOMXPath
    {
        $html = $this->get($url)->assertOk()->getContent();

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

    private function boxes(string $url): array
    {
        $xpath = $this->xpath($url);
        $found = [];

        foreach ($xpath->query('//label[contains(concat(" ", @class, " "), " c-facet ")]') as $row) {
            $input = $xpath->query('.//input[@type="checkbox"]', $row)->item(0);

            if (! $input instanceof DOMElement) {
                continue;
            }

            $count = $xpath->query('.//*[contains(concat(" ", @class, " "), " c-facet__count ")]', $row)->item(0);

            $found[] = [
                'field' => rtrim($input->getAttribute('name'), '[]'),
                'value' => $input->getAttribute('value'),
                'count' => (int) trim($count?->textContent ?? '0'),
                'disabled' => $input->hasAttribute('disabled'),
            ];
        }

        return $found;
    }

    private function resultTotal(string $url, string $id): int
    {
        $node = $this->xpath($url)->query('//*[@id="'.$id.'"]')->item(0);

        $this->assertNotNull($node, 'No element with id '.$id.' on '.$url);

        // The newsroom says "Showing 1 to 12 of 38" and states its total.
        if ($node instanceof \DOMElement && $node->hasAttribute('data-total')) {
            return (int) $node->getAttribute('data-total');
        }

        // "1 of 12 documents match your filters" -- the first number.
        preg_match('/\d[\d,]*/', $node->textContent, $m);

        return (int) str_replace(',', '', $m[0] ?? '0');
    }

    /**
     * The promise, on the library. Every option, every facet.
     */
    public function test_every_library_facet_count_is_what_the_click_returns(): void
    {
        $boxes = $this->boxes('/en/publications');

        $this->assertNotEmpty($boxes, 'The library rendered no facet options.');

        foreach ($boxes as $box) {
            $url = '/en/publications?'.$box['field'].'[]='.rawurlencode($box['value']);
            $actual = $this->resultTotal($url, 'library-count');

            $this->assertSame(
                $box['count'],
                $actual,
                $box['field'].' = '.$box['value'].' printed '.$box['count'].' and returned '.$actual
            );
        }
    }

    public function test_every_newsroom_facet_count_is_what_the_click_returns(): void
    {
        $boxes = $this->boxes('/en/newsroom');

        $this->assertNotEmpty($boxes, 'The newsroom rendered no facet options.');

        foreach ($boxes as $box) {
            $url = '/en/newsroom?'.$box['field'].'[]='.rawurlencode($box['value']);
            $actual = $this->resultTotal($url, 'news-count');

            $this->assertSame(
                $box['count'],
                $actual,
                $box['field'].' = '.$box['value'].' printed '.$box['count'].' and returned '.$actual
            );
        }
    }

    /**
     * The promise again, from a state that already has a filter on.
     *
     * This is the case a single grouped query gets wrong. With Sector
     * already narrowed, the number beside a second sector must be the size
     * of the two-sector result, not the size of the second sector alone.
     */
    public function test_the_counts_stay_honest_once_a_facet_has_a_selection(): void
    {
        $first = collect($this->boxes('/en/publications'))
            ->firstWhere(fn ($box) => $box['field'] === 'sector' && ! $box['disabled']);

        $this->assertNotNull($first, 'No sector option was available to select.');

        $base = '/en/publications?sector[]='.rawurlencode($first['value']);

        foreach ($this->boxes($base) as $box) {
            if ($box['field'] !== 'sector' || $box['value'] === $first['value']) {
                continue;
            }

            $url = $base.'&sector[]='.rawurlencode($box['value']);
            $actual = $this->resultTotal($url, 'library-count');

            $this->assertSame(
                $box['count'],
                $actual,
                'sector + '.$box['value'].' printed '.$box['count'].' and returned '.$actual
            );
        }
    }

    /** Two values in one facet widen the result. They do not narrow it. */
    public function test_a_second_value_in_one_facet_widens_the_result(): void
    {
        $one = $this->resultTotal('/en/publications?type[]=guideline', 'library-count');
        $two = $this->resultTotal('/en/publications?type[]=guideline&type[]=annual_report', 'library-count');

        $this->assertGreaterThan($one, $two);
    }

    /** Two facets narrow it. */
    public function test_two_facets_narrow_the_result(): void
    {
        $one = $this->resultTotal('/en/publications?type[]=guideline', 'library-count');

        $sector = Publication::listed()
            ->where('publication_type', 'guideline')
            ->whereNotNull('sector_id')
            ->with('sector')
            ->first()?->sector?->slug;

        $this->assertNotNull($sector);

        $two = $this->resultTotal('/en/publications?type[]=guideline&sector[]='.$sector, 'library-count');

        $this->assertLessThanOrEqual($one, $two);
    }

    /**
     * An option that would return nothing keeps its place and is disabled.
     * A reader who cannot see that Comoros has no publications cannot tell
     * whether it has none or whether the filter is broken.
     */
    public function test_an_option_that_returns_nothing_is_present_and_disabled(): void
    {
        $empty = collect($this->boxes('/en/publications'))->firstWhere('count', 0);

        $this->assertNotNull($empty, 'No facet option returned zero, so this test proved nothing.');
        $this->assertTrue($empty['disabled']);
    }

    /* The floor: a form that works with no script at all ----------------- */

    public function test_the_sidebar_is_a_get_form_with_a_real_submit_button(): void
    {
        $xpath = $this->xpath('/en/publications');

        $form = $xpath->query('//form[contains(concat(" ", @class, " "), " c-library__side ")]')->item(0);
        $this->assertNotNull($form, 'The sidebar is not a form.');
        $this->assertSame('get', strtolower($form->getAttribute('method')));

        // Removed by filters.js only after its listeners are attached, so a
        // script that throws leaves a working form behind.
        $this->assertSame(1, $xpath->query('//*[@data-live-submit]')->length);
    }

    /** Submitting the form is the same request a checkbox link would make. */
    public function test_the_form_submission_path_filters(): void
    {
        $total = $this->resultTotal('/en/publications?type%5B%5D=guideline', 'library-count');

        $this->assertSame(
            Publication::listed()->where('publication_type', 'guideline')->count(),
            $total
        );
    }

    /**
     * The script path asks for the same URL with an XHR header and swaps
     * three regions out of the response. All three have to be in it.
     */
    public function test_the_script_path_returns_every_region_it_swaps(): void
    {
        foreach ([
            '/en/publications?type[]=guideline' => ['library-filters', 'library-results', 'library-count'],
            '/en/newsroom?category[]=news' => ['news-filters', 'news-results', 'news-count'],
        ] as $url => $regions) {
            $html = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get($url)->assertOk()->getContent();

            foreach ($regions as $region) {
                $this->assertSame(
                    1,
                    substr_count($html, 'id="'.$region.'"'),
                    $region.' is missing from '.$url
                );
            }
        }
    }

    /** A link written before the sidebar existed still resolves. */
    public function test_a_scalar_filter_still_works(): void
    {
        $this->assertSame(
            $this->resultTotal('/en/publications?type[]=guideline', 'library-count'),
            $this->resultTotal('/en/publications?type=guideline', 'library-count')
        );
    }

    /** Active filters are listed with a way to take each one off. */
    public function test_an_active_filter_is_named_and_removable(): void
    {
        $xpath = $this->xpath('/en/publications?type[]=guideline&sector[]=postal');

        $chips = $xpath->query('//a[contains(concat(" ", @class, " "), " c-active__chip ")]');
        $this->assertSame(2, $chips->length);

        // Each chip removes its own filter and leaves the other on. Found
        // by its text rather than by its position, because the order of the
        // facets is a design decision and not this test's business.
        $hrefs = [];

        foreach ($chips as $chip) {
            $hrefs[trim(preg_replace('/\s+/u', ' ', $chip->textContent))] = $chip->getAttribute('href');
        }

        $guideline = collect($hrefs)->first(fn ($href, $label) => str_contains($label, 'Guideline'));
        $postal = collect($hrefs)->first(fn ($href, $label) => str_contains($label, 'Postal'));

        $this->assertNotNull($guideline, 'No chip named the type filter: '.implode(' | ', array_keys($hrefs)));
        $this->assertNotNull($postal, 'No chip named the sector filter: '.implode(' | ', array_keys($hrefs)));

        $this->assertStringNotContainsString('guideline', $guideline);
        $this->assertStringContainsString('postal', $guideline);

        $this->assertStringNotContainsString('postal', $postal);
        $this->assertStringContainsString('guideline', $postal);
    }

    public function test_the_sidebar_renders_in_every_language(): void
    {
        foreach (Locales::SUPPORTED as $locale) {
            foreach (['publications', 'newsroom'] as $section) {
                $xpath = $this->xpath("/$locale/$section");

                $this->assertGreaterThan(0, $xpath->query('//form[contains(concat(" ", @class, " "), " c-library__side ")]')->length, "$locale/$section");
                $this->assertGreaterThan(0, $xpath->query('//*[contains(concat(" ", @class, " "), " c-facets__legend ")]')->length, "$locale/$section");
            }
        }
    }

    /**
     * Question 6, ruled 9 September 2026: the footer column headings are
     * the only tracked caps on the platform. Screen 07 draws the facet
     * headings tracked and uppercase and the ruling overrides it.
     */
    public function test_the_facet_headings_are_not_tracked_caps(): void
    {
        $css = file_get_contents(dirname(__DIR__, 2).'/public/css/blocks.css');

        preg_match('/\.c-facets__legend\s*\{([^}]*)\}/', $css, $m);

        $this->assertNotEmpty($m, '.c-facets__legend has no rule.');
        $this->assertStringNotContainsString('text-transform', $m[1]);
        $this->assertStringNotContainsString('letter-spacing', $m[1]);
    }
}

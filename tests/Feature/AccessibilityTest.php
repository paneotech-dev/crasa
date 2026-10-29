<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Locales;
use Database\Seeders\CountrySeeder;
use Database\Seeders\HomepageSeeder;
use Database\Seeders\NewsroomSeeder;
use Database\Seeders\PublicationSeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * WCAG 2.1 AA is a contractual acceptance criterion, not an aspiration.
 *
 * These are the parts a test can hold. Contrast is computed separately
 * against the token values; the four-width and keyboard passes need a real
 * browser and are reported as reasoning, not measurement.
 */
class AccessibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(HomepageSeeder::class);
        $this->seed(PublicPagesSeeder::class);
        $this->seed(NewsroomSeeder::class);
        $this->seed(PublicationSeeder::class);
    }

    /** @return list<string> */
    private function pages(): array
    {
        $paths = ['', '/about', '/committees', '/members', '/contact', '/newsroom', '/publications', '/login'];
        $urls = [];
        foreach (Locales::SUPPORTED as $locale) {
            foreach ($paths as $path) {
                $urls[] = '/'.$locale.$path;
            }
        }

        return $urls;
    }

    /**
     * Every form field has a real label. A placeholder disappears the moment
     * a reader starts typing, which is exactly when they need it, and a
     * screen reader gets nothing to announce.
     */
    public function test_every_form_field_has_a_real_label(): void
    {
        $unlabelled = [];

        foreach ($this->pages() as $page) {
            $html = $this->get($page)->getContent();

            preg_match_all('/<(input|select|textarea)\b[^>]*>/i', $html, $fields);
            preg_match_all('/<label[^>]*\sfor="([^"]+)"/i', $html, $labels);
            $labelled = $labels[1];

            foreach ($fields[0] as $field) {
                if (preg_match('/type="(hidden|submit|button)"/i', $field)) {
                    continue;
                }
                // A field may instead carry its own accessible name.
                if (preg_match('/aria-label(?:ledby)?="/i', $field)) {
                    continue;
                }

                if (! preg_match('/\sid="([^"]+)"/i', $field, $id)) {
                    $unlabelled[] = $page.' -> field with no id: '.substr($field, 0, 60);

                    continue;
                }

                if (! in_array($id[1], $labelled, true)) {
                    $unlabelled[] = $page.' -> #'.$id[1];
                }
            }
        }

        $this->assertSame([], $unlabelled, "Fields with no label:\n".implode("\n", $unlabelled));
    }

    public function test_no_placeholder_stands_in_for_a_label(): void
    {
        $offenders = [];

        foreach ($this->pages() as $page) {
            $html = $this->get($page)->getContent();

            preg_match_all('/<input\b[^>]*placeholder="[^"]+"[^>]*>/i', $html, $fields);
            preg_match_all('/<label[^>]*\sfor="([^"]+)"/i', $html, $labels);

            foreach ($fields[0] as $field) {
                if (preg_match('/\sid="([^"]+)"/i', $field, $id) && ! in_array($id[1], $labels[1], true)) {
                    $offenders[] = $page.' -> #'.$id[1];
                }
            }
        }

        $this->assertSame([], $offenders, "Placeholders standing in for labels:\n".implode("\n", $offenders));
    }

    public function test_every_page_carries_exactly_one_h1(): void
    {
        $wrong = [];

        foreach ($this->pages() as $page) {
            $count = substr_count($this->get($page)->getContent(), '<h1');
            if ($count !== 1) {
                $wrong[] = $page.' has '.$count;
            }
        }

        $this->assertSame([], $wrong, "Pages without exactly one h1:\n".implode("\n", $wrong));
    }

    public function test_every_page_opens_with_a_skip_link_and_a_language(): void
    {
        foreach ($this->pages() as $page) {
            $html = $this->get($page)->getContent();

            $this->assertStringContainsString('class="c-skip"', $html, $page.' has no skip link.');
            $this->assertMatchesRegularExpression('/<html lang="(en|fr|pt)"/', $html, $page.' declares no language.');
        }
    }

    public function test_every_image_carries_alt_text_or_is_marked_decorative(): void
    {
        $offenders = [];

        foreach ($this->pages() as $page) {
            preg_match_all('/<img\b[^>]*>/i', $this->get($page)->getContent(), $images);

            foreach ($images[0] as $img) {
                if (! preg_match('/\salt="/i', $img)) {
                    $offenders[] = $page.' -> '.substr($img, 0, 70);
                }
            }
        }

        $this->assertSame([], $offenders, "Images with no alt attribute:\n".implode("\n", $offenders));
    }

    public function test_no_interactive_element_removes_its_focus_ring(): void
    {
        foreach (['base.css', 'chrome.css', 'blocks.css', 'grid.css'] as $sheet) {
            $css = file_get_contents(public_path('css/'.$sheet));

            $this->assertStringNotContainsString('outline: none', $css, $sheet);
            $this->assertStringNotContainsString('outline:none', $css, $sheet);
            $this->assertStringNotContainsString('outline: 0', $css, $sheet);
        }

        // Day 2: a 2px blue-ink ring at 2px offset with a 2px white band,
        // replacing the 3px amber ring (logo blue #7A90C8 is 2.81:1 on the
        // tints, so the ring is #2F4F8F, the same as ui.css).
        $base = file_get_contents(public_path('css/base.css'));
        $this->assertStringContainsString('outline: 2px solid var(--focus-ring);', $base);
        $this->assertStringContainsString('outline-offset: 2px;', $base);
        $this->assertStringContainsString('box-shadow: 0 0 0 2px var(--focus-halo);', $base);
        $this->assertStringContainsString('--focus-ring: var(--u-focus, var(--c-blue-ink, var(--c-navy)));', $base);
    }

    public function test_reduced_motion_is_honoured(): void
    {
        $css = file_get_contents(public_path('css/base.css'));

        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $css);
        $this->assertStringContainsString('animation-duration: .01ms !important', $css);
    }

    public function test_no_stylesheet_animates_on_scroll(): void
    {
        // Motion answers a user action; it does not decorate scrolling.
        foreach (['base.css', 'chrome.css', 'blocks.css'] as $sheet) {
            $css = file_get_contents(public_path('css/'.$sheet));

            $this->assertStringNotContainsString('@keyframes', $css, $sheet.' defines an animation.');
        }
    }

    public function test_a_print_stylesheet_exists_and_keeps_what_a_regulator_prints_for(): void
    {
        $css = file_get_contents(public_path('css/print.css'));

        // Chrome that means nothing on paper is dropped.
        $this->assertStringContainsString('.c-header', $css);
        $this->assertStringContainsString('.c-pagination', $css);
        // A printed link is followable.
        $this->assertStringContainsString('attr(href)', $css);
        // Table headers repeat across pages.
        $this->assertStringContainsString('display: table-header-group', $css);
        // The superseded warning survives, or a reader cites a repealed
        // document from paper.
        $this->assertStringContainsString('.c-notice', $css);
        $this->assertStringContainsString('.c-pill', $css);
    }

    public function test_the_print_stylesheet_is_linked_on_every_page(): void
    {
        foreach ($this->pages() as $page) {
            $this->assertStringContainsString(
                'css/print.css',
                $this->get($page)->getContent(),
                $page.' has no print stylesheet.'
            );
        }
    }

    public function test_charts_and_matrices_have_a_table_equivalent(): void
    {
        // The comparator must be usable without seeing the graph, and the
        // permission matrix without seeing the colour.
        $html = $this->actingAs(User::factory()->administrator()->create())
            ->get('/en/admin/users')->getContent();

        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('Granted', $html);
        $this->assertStringContainsString('Not granted', $html);
    }

    public function test_every_table_has_a_caption(): void
    {
        $html = $this->actingAs(User::factory()->administrator()->create())
            ->get('/en/admin/audit')->getContent();

        $this->assertStringContainsString('<caption', $html);
    }
}

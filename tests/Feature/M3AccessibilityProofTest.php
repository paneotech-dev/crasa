<?php

namespace Tests\Feature;

use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\HomepageSeeder;
use Database\Seeders\LegalPagesSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\NewsroomSeeder;
use Database\Seeders\PublicationSeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Accessibility proof for the client's item A, held as tests so it stays
 * proved after the axe run: one h1, the page language, the skip link first,
 * alt on every image, a label on every control, the four landmarks, and the
 * two brand pairings the client named, with their measured ratios.
 */
class M3AccessibilityProofTest extends TestCase
{
    use RefreshDatabase;

    private const KEY_PAGES = ['', '/publications', '/comparator', '/contact', '/events', '/about', '/members', '/newsroom', '/privacy', '/accessibility'];

    private const ENGLISH_ONLY = ['/committees', '/members/bw', '/projects', '/terms', '/login'];

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([CountrySeeder::class, SectorSeeder::class, CommitteeSeeder::class, MenuSeeder::class, HomepageSeeder::class,
            PublicPagesSeeder::class, LegalPagesSeeder::class, NewsroomSeeder::class, PublicationSeeder::class] as $seeder) {
            $this->seed($seeder);
        }
    }

    /** @return array<string, string> url => locale */
    private function pages(): array
    {
        $pages = [];
        foreach (['en', 'fr', 'pt'] as $locale) {
            foreach (self::KEY_PAGES as $path) {
                $pages['/'.$locale.$path] = $locale;
            }
        }
        foreach (self::ENGLISH_ONLY as $path) {
            $pages['/en'.$path] = 'en';
        }

        return $pages;
    }

    /** @var array<string, string> rendered pages, shared by the tests: the seed is the same for each */
    private static array $html = [];

    private function dom(string $url): DOMXPath
    {
        if (! isset(self::$html[$url])) {
            $response = $this->get($url);
            $this->assertSame(200, $response->getStatusCode(), $url.' did not answer 200.');
            self::$html[$url] = $response->getContent();
        }
        $doc = new DOMDocument;
        libxml_use_internal_errors(true);
        $doc->loadHTML(self::$html[$url]);
        libxml_clear_errors();

        return new DOMXPath($doc);
    }

    public function test_every_public_page_has_exactly_one_h1(): void
    {
        $wrong = [];
        foreach (array_keys($this->pages()) as $url) {
            $count = $this->dom($url)->query('//h1')->length;
            if ($count !== 1) {
                $wrong[] = "{$url}: {$count}";
            }
        }
        $this->assertSame([], $wrong);
    }

    public function test_the_html_lang_follows_the_route_locale(): void
    {
        $wrong = [];
        foreach ($this->pages() as $url => $locale) {
            $lang = $this->dom($url)->query('/html/@lang')->item(0)?->nodeValue;
            if ($lang !== $locale) {
                $wrong[] = "{$url}: {$lang}";
            }
        }
        $this->assertSame([], $wrong);
    }

    public function test_the_skip_link_is_the_first_focusable_element_and_targets_main(): void
    {
        $wrong = [];
        foreach (array_keys($this->pages()) as $url) {
            $xpath = $this->dom($url);
            $first = $xpath->query('//body//*[(self::a and @href) or self::button or self::input[not(@type="hidden")] or self::select or self::textarea or (@tabindex and @tabindex!="-1")]')->item(0);
            if (! $first instanceof DOMElement || ! str_contains($first->getAttribute('class'), 'c-skip')) {
                $wrong[] = $url.': first focusable is '.($first?->nodeName ?? 'none');

                continue;
            }
            $target = ltrim($first->getAttribute('href'), '#');
            if ($xpath->query('//main[@id="'.$target.'"] | //*[@id="'.$target.'"][ancestor-or-self::main]')->length === 0) {
                $wrong[] = $url.': skip target #'.$target.' is not main';
            }
        }
        $this->assertSame([], $wrong);
    }

    public function test_the_translation_notice_is_a_named_region_first_in_main(): void
    {
        foreach (['/fr', '/pt', '/fr/contact', '/pt/publications'] as $url) {
            $xpath = $this->dom($url);
            $notice = $xpath->query('//main/*[1][@data-translation-notice]')->item(0);
            $this->assertInstanceOf(DOMElement::class, $notice, $url.': the notice is not first in main.');
            $this->assertSame('region', $notice->getAttribute('role'));
            $title = $xpath->query('//*[@id="'.$notice->getAttribute('aria-labelledby').'"]')->item(0);
            $this->assertNotSame('', trim((string) $title?->textContent), $url.': the notice region has no name.');
        }
        $this->assertSame(0, $this->dom('/en')->query('//*[@data-translation-notice]')->length);
    }

    public function test_every_image_has_an_alt_attribute(): void
    {
        $wrong = [];
        foreach (array_keys($this->pages()) as $url) {
            foreach ($this->dom($url)->query('//img[not(@alt)]') as $img) {
                $wrong[] = $url.': '.$img->getAttribute('src');
            }
        }
        $this->assertSame([], $wrong);
    }

    public function test_every_form_control_has_a_label(): void
    {
        $wrong = [];
        foreach (array_keys($this->pages()) as $url) {
            $xpath = $this->dom($url);
            $controls = $xpath->query('//input[not(@type="hidden" or @type="submit" or @type="button" or @type="reset" or @type="image")] | //select | //textarea');
            foreach ($controls as $control) {
                $id = $control->getAttribute('id');
                $named = $control->hasAttribute('aria-label') || $control->hasAttribute('aria-labelledby') || $control->hasAttribute('title')
                    || $xpath->query('ancestor::label', $control)->length > 0
                    || ($id !== '' && $xpath->query('//label[@for="'.$id.'"]')->length > 0);
                if (! $named) {
                    $wrong[] = $url.': '.$control->nodeName.' '.($control->getAttribute('name') ?: $id);
                }
            }
        }
        $this->assertSame([], $wrong);
    }

    public function test_every_public_page_has_the_four_landmarks(): void
    {
        $wrong = [];
        foreach (array_keys($this->pages()) as $url) {
            $xpath = $this->dom($url);
            foreach (['header' => '//header[not(ancestor::main or ancestor::article or ancestor::section)] | //*[@role="banner"]',
                'nav' => '//nav | //*[@role="navigation"]',
                'main' => '//main | //*[@role="main"]',
                'footer' => '//footer[not(ancestor::main or ancestor::article or ancestor::section)] | //*[@role="contentinfo"]'] as $name => $query) {
                if ($xpath->query($query)->length === 0) {
                    $wrong[] = "{$url}: no {$name}";
                }
            }
            if ($xpath->query('//main | //*[@role="main"]')->length !== 1) {
                $wrong[] = "{$url}: more than one main";
            }
        }
        $this->assertSame([], $wrong);
    }

    /**
     * The two pairings the client named, measured on the token values the
     * stylesheets use. The ratio is in the failure message and in the proof
     * README.
     */
    public function test_green_on_white_and_navy_on_pale_green_clear_aa(): void
    {
        preg_match_all('/(--c-[a-z0-9-]+)\s*:\s*(#[0-9A-Fa-f]{6})/', file_get_contents(public_path('css/tokens.css')), $m, PREG_SET_ORDER);
        $tokens = [];
        foreach ($m as $match) {
            $tokens[$match[1]] = $match[2];
        }

        $pairs = [
            // Text: links, buttons and current menu items in deep green on white.
            ['green-dark text on white', '--c-green-dark', '--c-white', 4.5],
            // Text: navy headings and the CRASA blue on the pale green tint.
            ['navy text on pale green', '--c-navy', '--c-green-light', 4.5],
            ['crasa blue text on pale green', '--c-crasa-blue', '--c-green-light', 4.5],
            ['green-dark text on pale green', '--c-green-dark', '--c-green-light', 4.5],
            // Non-text: the brand green as a control fill or chart series on white.
            ['brand green fill on white', '--c-green', '--c-white', 3.0],
        ];

        foreach ($pairs as [$name, $fg, $bg, $min]) {
            $ratio = $this->ratio($tokens[$fg], $tokens[$bg]);
            $this->assertGreaterThanOrEqual($min, $ratio, sprintf('%s is %.2f:1, needs %.1f:1.', $name, $ratio, $min));
        }

        // The brand green as text on white is below 4.5:1 and must not be
        // used as body text: no public stylesheet sets it as a text colour.
        $this->assertLessThan(4.5, $this->ratio($tokens['--c-green'], $tokens['--c-white']));
        // (A rule scoped to the navy ground is not on white and is left out.)
        foreach (['css/ui.css', 'css/blocks.css', 'css/k.css'] as $css) {
            foreach (file(public_path($css)) as $n => $line) {
                if (! str_contains($line, 'data-ground="navy"')) {
                    $this->assertDoesNotMatchRegularExpression('/(?<![-a-z])color\s*:\s*var\(--c-green\)/', $line, $css.':'.($n + 1).' sets the brand green as a text colour.');
                }
            }
        }
    }

    /**
     * The home page's "all items" links sit on the pale and card grounds of
     * the home design (k.css). The base green measured 4.05:1 and 4.24:1
     * there; the links take the hover green.
     */
    public function test_the_home_all_items_links_clear_aa_on_their_grounds(): void
    {
        $css = file_get_contents(public_path('css/k.css'));
        preg_match_all('/(--k-[a-z0-9-]+)\s*:\s*(#[0-9A-Fa-f]{6})/', $css, $m, PREG_SET_ORDER);
        $k = [];
        foreach ($m as $match) {
            $k[$match[1]] ??= $match[2];
        }
        $this->assertMatchesRegularExpression('/\.k-all\s*\{[^}]*color:\s*var\(--k-green-hover\)/', $css);
        foreach (['--k-pale', '--k-card', '--k-white'] as $ground) {
            if (! isset($k[$ground])) {
                continue;
            }
            $ratio = $this->ratio($k['--k-green-hover'], $k[$ground]);
            $this->assertGreaterThanOrEqual(4.5, $ratio, sprintf('.k-all on %s is %.2f:1.', $ground, $ratio));
        }
    }

    /**
     * While the consent banner shows, the floating controls sit above it and
     * the page scrolls focused content clear of it; the footer leaves room
     * for both floating squares so "Back to top" never covers a footer link.
     */
    public function test_the_consent_banner_never_covers_the_floating_controls_or_focus(): void
    {
        $js = file_get_contents(public_path('js/consent.js'));
        $this->assertStringContainsString("setProperty('--cc-h'", $js);
        $this->assertMatchesRegularExpression('/function hideBanner\(\)[^}]*banner\.hidden = true;\s*setBannerHeight\(\);/s', $js);
        $this->assertStringContainsString("addEventListener('resize', setBannerHeight)", $js);

        $css = file_get_contents(public_path('css/a11y.css'));
        $this->assertMatchesRegularExpression('/\.c-float \{[^}]*bottom:[^;]*var\(--cc-h, 0px\)/', $css);
        $this->assertStringContainsString('html { scroll-padding-bottom: var(--cc-h, 0px); }', $css);
        $this->assertStringContainsString('.k-foot { padding-bottom: calc(88px + var(--s-2) + var(--s-6)', $css);
    }

    private function ratio(string $a, string $b): float
    {
        $la = $this->luminance($a);
        $lb = $this->luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    private function luminance(string $hex): float
    {
        $c = [];
        foreach ([1, 3, 5] as $o) {
            $v = hexdec(substr($hex, $o, 2)) / 255;
            $c[] = $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }
}

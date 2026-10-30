<?php

namespace Tests\Feature;

use App\Enums\Clearance;
use App\Models\Meeting;
use App\Models\SocialPost;
use Database\Seeders\CountrySeeder;
use Database\Seeders\HeaderMenuSeeder;
use Database\Seeders\HomepageSeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Database\Seeders\SiteSettingSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Item 8f, the polish pass on the public site: the home work cards in
 * CRASA's wording with uneven lengths, headings in CRASA's words, no
 * eyebrow outside the hero, no "X, one Y" heading, the footer's Members
 * column and the one-link calendar on meeting cards.
 */
class PublicPolishTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(PublicPagesSeeder::class);
        $this->seed(SiteSettingSeeder::class);
        $this->seed(HomepageSeeder::class);
        $this->seed(HeaderMenuSeeder::class);
    }

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();

        return new DOMXPath($dom);
    }

    public function test_no_two_home_work_cards_have_word_counts_within_ten_percent(): void
    {
        foreach (['en', 'fr', 'pt'] as $locale) {
            $html = $this->get('/'.$locale)->assertOk()->getContent();
            $counts = [];

            foreach ($this->xpath($html)->query('//article[contains(@class, "k-act")]/p') as $p) {
                $counts[] = count(preg_split('/\s+/u', trim($p->textContent), -1, PREG_SPLIT_NO_EMPTY));
            }

            $this->assertCount(4, $counts, $locale.': four work cards expected.');

            foreach ($counts as $i => $a) {
                foreach (array_slice($counts, $i + 1) as $b) {
                    $this->assertGreaterThan(0.10, abs($a - $b) / max($a, $b), "{$locale}: work cards of {$a} and {$b} words are within 10 percent.");
                }
            }
        }
    }

    public function test_the_work_cards_carry_crasa_wording_and_no_icon(): void
    {
        $html = $this->get('/en')->assertOk()->getContent();

        $this->assertStringContainsString('<h2 id="k-work-h">Our work</h2>', $html);
        $this->assertStringContainsString('CRASA provides the model guidelines, recommendations and position papers', $html);
        $this->assertStringContainsString('Global Monitoring System', $html);
        $this->assertStringNotContainsString('k-act__ic', $html);
        $this->assertStringNotContainsString('k-ty__i', $html);
    }

    public function test_headings_are_in_crasa_words_and_no_eyebrow_sits_outside_the_hero(): void
    {
        $html = $this->get('/en')->assertOk()->getContent();

        $this->assertStringContainsString('Member regulators</h2>', $html);
        $this->assertStringContainsString('CRASA in figures</h2>', $html);
        $this->assertStringNotContainsString('Four lines of work', $html);
        $this->assertStringNotContainsString('Fourteen national authorities', $html);

        foreach (['Key figures', 'Our mission', 'Members and regional cooperation', 'Every document is held by CRASA', 'The table and the chart change'] as $gone) {
            $this->assertStringNotContainsString($gone, $html);
        }

        // Every k-lbl and u-eyebrow on the page sits inside the hero section.
        $x = $this->xpath($html);
        $outside = $x->query('//*[contains(concat(" ", normalize-space(@class), " "), " k-lbl ") or contains(concat(" ", normalize-space(@class), " "), " u-eyebrow ")][not(ancestor::section[contains(@class, "k-hero")])][not(ancestor::header)][not(ancestor::*[contains(@class, "k-mega")])]');
        $this->assertSame(0, $outside->length, 'An eyebrow label sits outside the hero.');
    }

    public function test_no_heading_or_string_uses_the_x_comma_one_y_pattern(): void
    {
        $pattern = '/\b[A-Z][a-z]+ [a-z]+ of [a-z]+, one [a-z]+ [a-z]+|national (regulators|authorities), one regional|\bQuatre axes de travail, un\b|\bQuatro linhas de trabalho, um\b|nacionais, um quadro|nationaux, un cadre|One register, fourteen|Un registre, quatorze|Um registo, catorze/u';

        foreach (['en', 'fr', 'pt'] as $locale) {
            foreach (glob(lang_path($locale.'/*.php')) as $file) {
                $this->assertDoesNotMatchRegularExpression($pattern, file_get_contents($file), $file);
            }
            foreach (['', '/about', '/members'] as $path) {
                $this->assertDoesNotMatchRegularExpression($pattern, $this->get('/'.$locale.$path)->assertOk()->getContent(), $locale.$path);
            }
        }
    }

    public function test_the_hero_tiles_are_read_from_the_data(): void
    {
        $meeting = Meeting::factory()->create([
            'clearance' => Clearance::Public,
            'starts_on' => now()->addDays(12)->startOfDay(),
            'ends_on' => now()->addDays(14)->startOfDay(),
            'date_unknown' => false,
            'location' => 'Gaborone, Botswana',
        ]);

        $html = $this->get('/en')->assertOk()->getContent();

        $this->assertStringContainsString('Next meeting', $html);
        $this->assertStringContainsString($meeting->starts_on->isoFormat('D MMMM').', Gaborone', $html);
        $this->assertStringContainsString('/en/events/'.$meeting->slug, $html);
    }

    public function test_the_footer_lists_members_and_keeps_the_back_office_out(): void
    {
        $html = $this->get('/en')->assertOk()->getContent();
        $x = $this->xpath($html);
        $column = $x->query('//nav[contains(@class, "k-smap")]//div[h2[normalize-space()="Members"]]/a');
        $labels = array_map(fn ($a) => trim($a->textContent), iterator_to_array($column));

        // 30 October: the column also carries the member states, taken out
        // of the Publications column so each link appears once.
        $this->assertContains('Member states', $labels);
        $this->assertContains('Member directory', $labels);
        $this->assertContains('Contact', $labels);
        $this->assertCount(4, $labels);
        $this->assertSame(0, $x->query('//nav[contains(@class, "k-smap")]//a[normalize-space()="Back office"]')->length);
        $this->assertSame(0, $x->query('//nav[contains(@class, "k-smap")]//a[normalize-space()="Accessibility statement"]')->length);
        $this->assertSame(1, $x->query('//div[contains(@class, "k-fbar")]//a[normalize-space()="Accessibility statement"]')->length);
    }

    public function test_a_meeting_card_has_one_add_to_calendar_link_and_no_public_badge(): void
    {
        $meeting = Meeting::factory()->create([
            'clearance' => Clearance::Public,
            'starts_on' => now()->addDays(20)->startOfDay(),
            'ends_on' => now()->addDays(21)->startOfDay(),
            'date_unknown' => false,
        ]);

        $html = $this->get('/en/events')->assertOk()->getContent();
        $x = $this->xpath($html);

        $this->assertGreaterThan(0, $x->query('//article[contains(@class, "e-card")]//a[contains(@class, "e-addcal__link")][contains(@href, "#add-to-calendar")]')->length);
        $this->assertSame(0, $x->query('//article[contains(@class, "e-card")]//a[contains(@class, "e-addcal__action")]')->length);
        $this->assertSame(0, $x->query('//article[contains(@class, "e-card")]//span[contains(@class, "u-pill")][normalize-space()="Public"]')->length);

        $show = $this->get('/en/events/'.$meeting->slug)->assertOk()->getContent();
        $this->assertStringContainsString('id="add-to-calendar"', $show);
        $this->assertSame(3, substr_count($show, '<a class="e-addcal__action"'));
    }

    public function test_a_social_card_has_one_caption_and_no_second_link(): void
    {
        SocialPost::create([
            'network' => 'linkedin',
            'url' => 'https://www.linkedin.com/feed/update/urn:li:activity:1',
            'embed_url' => 'https://www.linkedin.com/embed/feed/update/urn:li:activity:1',
            'posted_at' => now()->startOfYear()->addDays(300),
            'status' => 'published',
        ]);

        $html = $this->get('/en')->assertOk()->getContent();

        $this->assertStringContainsString('CRASA on social media', $html);
        $this->assertStringContainsString('Posted on LinkedIn, '.now()->startOfYear()->addDays(300)->isoFormat('D MMMM'), $html);
        $this->assertStringNotContainsString('Open the post on LinkedIn', $html);
    }

    public function test_cards_lose_their_shadow_and_lift_on_the_public_site(): void
    {
        $css = file_get_contents(public_path('css/k.css'));
        $block = substr($css, strpos($css, '/* ==== Day 3 polish'));

        $this->assertStringContainsString('#content:not(.a-main) .u-card:hover', $block);
        $this->assertStringContainsString('box-shadow: none; transform: none;', $block);
        $this->assertStringContainsString('.k-fig { border-left: 0;', $block);
    }
}

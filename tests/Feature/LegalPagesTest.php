<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Support\Locales;
use Database\Seeders\LegalPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The three legal pages.
 *
 * All three are linked from the footer of every page on the site, so none of
 * them may be a holding line in front of fourteen regulators.
 *
 * They are drafts and they say so. The distinction that matters is between
 * describing what the platform does — which the build knows and can write —
 * and making a legal commitment, which it cannot. Retention periods,
 * governing law and jurisdiction are left as named blanks, because a wrong
 * retention period on a published privacy notice is a compliance failure
 * CRASA would inherit from us.
 */
class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    private const PAGES = ['privacy', 'terms', 'accessibility'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LegalPagesSeeder::class);
    }

    public function test_every_legal_page_resolves_in_every_language(): void
    {
        foreach (self::PAGES as $slug) {
            foreach (Locales::SUPPORTED as $locale) {
                $this->get('/'.$locale.'/'.$slug)->assertOk();
            }
        }
    }

    public function test_no_legal_page_shows_a_holding_line(): void
    {
        foreach (self::PAGES as $slug) {
            foreach (Locales::SUPPORTED as $locale) {
                $this->get('/'.$locale.'/'.$slug)
                    ->assertOk()
                    ->assertDontSee(__('pages.awaiting_content', [], $locale));
            }
        }
    }

    /** A reader must not mistake a draft for an approved text. */
    public function test_the_drafts_say_they_are_drafts(): void
    {
        $this->get('/en/privacy')->assertOk()->assertSee('DRAFT FOR CRASA REVIEW');
        $this->get('/en/terms')->assertOk()->assertSee('DRAFT FOR CRASA REVIEW');
        $this->get('/fr/privacy')->assertOk()->assertSee('PROJET SOUMIS');
        $this->get('/pt/terms')->assertOk()->assertSee('PROJETO PARA REVISÃO');
    }

    /**
     * The commitments the build must not invent are named blanks rather than
     * plausible-sounding text. A retention period we made up would read
     * exactly like one CRASA had decided.
     */
    public function test_every_legal_commitment_is_left_as_a_named_blank(): void
    {
        $privacy = $this->get('/en/privacy')->assertOk()->getContent();

        foreach (['retention periods', 'data protection officer', 'supervisory authority', 'hosting location'] as $blank) {
            $this->assertStringContainsStringIgnoringCase($blank, $privacy, 'The privacy notice does not raise: '.$blank);
        }

        $terms = $this->get('/en/terms')->assertOk()->getContent();

        foreach (['governing law', 'jurisdiction', 'liability'] as $blank) {
            $this->assertStringContainsStringIgnoringCase($blank, $terms, 'The terms do not raise: '.$blank);
        }

        // And each is bracketed as a decision, not asserted as a fact.
        $this->assertStringContainsString('CRASA to', $privacy);
        $this->assertStringContainsString('CRASA and its counsel to', $terms);
    }

    /** They stay on the placeholder list until CRASA signs them off. */
    public function test_the_drafts_remain_flagged_as_needing_content(): void
    {
        foreach (['privacy', 'terms'] as $slug) {
            $block = Page::where('slug', $slug)->sole()->blocks()->firstOrFail();

            $this->assertTrue($block->needs_content, $slug.' is a draft and is not flagged as needing content.');
            $this->assertNotEmpty($block->content_note);
        }

        $this->artisan('content:placeholders')
            ->expectsOutputToContain('privacy')
            ->expectsOutputToContain('terms');
    }

    /**
     * The accessibility statement is different: it is a statement about this
     * build, which the build knows. It must name the measured exceptions
     * rather than claim an unqualified pass.
     */
    public function test_the_accessibility_statement_names_its_exceptions(): void
    {
        $html = $this->get('/en/accessibility')->assertOk()->getContent();

        // The standard named, the version, and the level. Asserted
        // separately rather than as one phrase, so rewording the sentence
        // does not fail a test about whether the target is stated.
        $this->assertStringContainsString('WCAG', $html);
        $this->assertStringContainsString('2.1', $html);
        $this->assertStringContainsString('Level AA', $html);
        $this->assertStringContainsStringIgnoringCase('Known exceptions', $html);
        // The two token variations logged in OPEN_ITEMS.md, named rather
        // than glossed over.
        $this->assertStringContainsString('1.4.11', $html);
        $this->assertStringContainsString('1.4.3', $html);
    }

    /**
     * A privacy notice claiming there are no trackers has to be true. This
     * platform sets a session cookie and a language cookie and nothing else,
     * and the claim is checked rather than asserted.
     */
    public function test_the_no_tracker_claim_is_true(): void
    {
        // The privacy page itself: it is seeded here and it is the page
        // making the claim.
        $response = $this->get('/en/privacy')->assertOk();

        foreach ($response->headers->getCookies() as $cookie) {
            $this->assertStringNotContainsStringIgnoringCase('_ga', $cookie->getName());
            $this->assertStringNotContainsStringIgnoringCase('fbp', $cookie->getName());
        }

        $html = $response->getContent();

        foreach (['googletagmanager', 'google-analytics', 'connect.facebook', 'hotjar'] as $tracker) {
            $this->assertStringNotContainsString($tracker, $html, 'The privacy notice says there is no third-party tracker.');
        }
    }
}

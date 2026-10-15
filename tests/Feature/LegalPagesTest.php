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
 * They are drafts and the back office says so. The distinction that matters
 * is between describing what the platform does — which the build knows and
 * can write — and making a legal commitment, which it cannot. Retention periods,
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

    /**
     * THE PUBLIC PAGE CARRIES THE TEXT AND NOTHING ABOUT THE PROJECT.
     *
     * Until 9 September 2026 the privacy notice and the terms of use each
     * opened with a paragraph announcing themselves a draft CRASA had not
     * reviewed. That is a fact about the project rather than a fact about
     * privacy or about terms of use, and it met every public reader before
     * the text did. It moved to the back office, where the review state
     * belongs, and this test holds it there in all three languages.
     */
    public function test_no_public_legal_page_announces_its_own_draft_status(): void
    {
        $banners = ['DRAFT FOR CRASA REVIEW', 'PROJET SOUMIS', 'PROJETO PARA REVISÃO'];

        foreach (self::PAGES as $slug) {
            foreach (Locales::SUPPORTED as $locale) {
                $html = $this->get('/'.$locale.'/'.$slug)->assertOk()->getContent();

                foreach ($banners as $banner) {
                    $this->assertStringNotContainsString(
                        $banner,
                        $html,
                        $locale.'/'.$slug.' still announces itself a draft.'
                    );
                }

                // And no other spelling of the same idea reaches the page.
                $this->assertStringNotContainsString('needs_client_review', $html);
                $this->assertStringNotContainsStringIgnoringCase('awaiting crasa', $html);
            }
        }
    }

    /** The text itself stays. Removing the banner removed a banner. */
    public function test_the_text_survived_the_banner_going(): void
    {
        $this->get('/en/privacy')->assertOk()
            ->assertSee('What this notice covers', false)
            ->assertSee('There is no advertising cookie', false);

        $this->get('/en/terms')->assertOk()
            ->assertSee('What these terms cover', false);

        $this->get('/fr/privacy')->assertOk()->assertSee('avis de confidentialité', false);
        $this->get('/pt/terms')->assertOk()->assertSee('ecossistema digital da CRASA', false);
    }

    /**
     * The review state is administration only, and it names what is still
     * outstanding: governing law, retention periods, the data protection
     * contact. The list is read out of the page's own text, so filling a
     * blank in removes it rather than leaving a stale second list behind.
     */
    public function test_the_review_state_is_admin_side_and_lists_the_blanks(): void
    {
        foreach (['privacy', 'terms'] as $slug) {
            $page = Page::where('slug', $slug)->sole();

            $this->assertTrue($page->awaitsClientReview(), $slug.' is not flagged for client review.');
            $this->assertNotEmpty($page->outstandingClientItems(), $slug.' lists no outstanding item.');
        }

        $privacy = Page::where('slug', 'privacy')->sole()->outstandingClientItems();
        $terms = Page::where('slug', 'terms')->sole()->outstandingClientItems();

        $this->assertStringContainsStringIgnoringCase('retention periods', implode(' ', $privacy));
        $this->assertStringContainsStringIgnoringCase('data protection officer', implode(' ', $privacy));
        $this->assertStringContainsStringIgnoringCase('governing law', implode(' ', $terms));

        // The accessibility statement is a statement about this build, which
        // the build knows. It is not waiting on anybody.
        $this->assertFalse(Page::where('slug', 'accessibility')->sole()->awaitsClientReview());
    }

    /**
     * The commitments the build must not invent are named blanks rather than
     * plausible-sounding text. A retention period we made up would read
     * exactly like one CRASA had decided.
     */
    public function test_every_legal_commitment_is_left_as_a_named_blank(): void
    {
        // Named blanks for CRASA, in the stored text and in the back office's
        // outstanding list; not shown to readers (owner, 15 October 2026).
        $privacyItems = implode(' ', Page::where('slug', 'privacy')->sole()->outstandingClientItems());
        foreach (['retention periods', 'data protection officer', 'supervisory authority', 'hosting location'] as $blank) {
            $this->assertStringContainsStringIgnoringCase($blank, $privacyItems, 'The privacy notice does not raise: '.$blank);
        }

        $termsItems = implode(' ', Page::where('slug', 'terms')->sole()->outstandingClientItems());
        foreach (['governing law', 'jurisdiction', 'liability'] as $blank) {
            $this->assertStringContainsStringIgnoringCase($blank, $termsItems, 'The terms do not raise: '.$blank);
        }

        // A reader sees the document without the notes.
        foreach (['/en/privacy', '/fr/privacy', '/pt/privacy', '/en/terms', '/fr/terms', '/pt/terms'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertDoesNotMatchRegularExpression('/\[(CRASA|La CRASA|A CRASA) /u', strip_tags($html), $path);
        }
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

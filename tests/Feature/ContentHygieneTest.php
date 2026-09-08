<?php

namespace Tests\Feature;

use App\Support\Locales;
use Tests\TestCase;

/**
 * The rendered site carries only content CRASA would publish.
 *
 * Project, contract and programme information belongs in docs/ and in git,
 * never in a Blade template or a lang file. Partner and funder attribution
 * is supplied by the client and is never drafted or guessed.
 *
 * This test reads the full HTML of every public page in every locale, so it
 * covers page titles, meta descriptions, alt text and aria-labels as well as
 * visible copy.
 */
class ContentHygieneTest extends TestCase
{
    /**
     * Terms that must never reach a rendered page.
     *
     * @var list<string>
     */
    private const FORBIDDEN = [
        // Programme and funding
        'EGEE-ICT', 'EGEE',
        'European Union', 'Union européenne', 'União Europeia',
        'COMESA',
        'funded by', 'financé par', 'financiado pela',
        'donor', 'bailleur', 'doador',
        'visibility requirement',
        // Contract and procurement
        'ZM-COMESA', 'WEBSITE/25/07/2025',
        'Terms of Reference', 'Annexure', 'PANEOTECH',
        'deliverable', 'milestone', 'focal point',
        // Build and project process
        'is_sample', 'DATA_MODEL', 'BUILD_SPEC', 'CLAUDE.md',
        'build order', 'design foundations', 'token layer',
    ];

    /**
     * Short tokens that would false-positive as substrings. A build hash or
     * an element id can contain "d1"; only a standalone deliverable
     * reference should fail the test.
     *
     * @var list<string>
     */
    private const FORBIDDEN_PATTERNS = [
        '/\bD[1-5]\b/i',
        '/\bForm [1-3]\b/i',
        '/\bWS-\d\d\b/i',
        '/\bIN-\d\d\b/i',
        '/\bRP-\d\d\b/i',
    ];

    /** @return list<string> */
    private function publicPages(): array
    {
        $urls = [];

        foreach (Locales::SUPPORTED as $locale) {
            $urls[] = '/'.$locale;
        }

        return $urls;
    }

    public function test_no_page_leaks_contractual_funding_or_programme_information(): void
    {
        foreach ($this->publicPages() as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5);

            foreach (self::FORBIDDEN as $term) {
                $this->assertStringNotContainsStringIgnoringCase(
                    $term,
                    $decoded,
                    $url.' renders the term "'.$term.'". Project, contract and '
                        .'programme information belongs in docs/, not in output.'
                );
            }

            foreach (self::FORBIDDEN_PATTERNS as $pattern) {
                $this->assertDoesNotMatchRegularExpression(
                    $pattern,
                    $decoded,
                    $url.' renders something matching '.$pattern.'. Deliverable and '
                        .'work-stream references belong in docs/, not in output.'
                );
            }
        }
    }

    public function test_the_footer_carries_no_partner_or_funder_attribution(): void
    {
        foreach ($this->publicPages() as $url) {
            $html = $this->get($url)->getContent();

            $this->assertStringNotContainsString('c-footer__emblem', $html);
            $this->assertStringNotContainsString('c-footer__donor', $html);
        }
    }

    public function test_the_removed_translation_keys_are_gone_rather_than_blanked(): void
    {
        foreach (Locales::SUPPORTED as $locale) {
            $footer = require lang_path($locale.'/footer.php');

            $this->assertArrayNotHasKey('donor_statement', $footer);
            $this->assertArrayNotHasKey('partners_label', $footer);
        }
    }

    /**
     * A blanked key still renders as an empty element and still ships the
     * string to the client. Removal has to be removal.
     */
    public function test_no_lang_file_mentions_a_funder_or_the_programme(): void
    {
        foreach (glob(lang_path('*/*.php')) as $file) {
            $contents = file_get_contents($file);

            foreach (['EGEE', 'COMESA', 'European Union', 'Union européenne', 'União Europeia'] as $term) {
                $this->assertStringNotContainsString(
                    $term,
                    $contents,
                    basename(dirname($file)).'/'.basename($file).' mentions '.$term.'.'
                );
            }
        }
    }
}

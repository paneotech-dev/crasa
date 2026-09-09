<?php

namespace Tests\Feature;

use App\Models\Authority;
use App\Models\PageBlock;
use Database\Seeders\AuthoritySeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\LegalPagesSeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The documents that go to the client.
 *
 * These are not code, but they are deliverables, and a variation note that
 * silently falls out of step with the platform is worse than no variation
 * note: it turns a disclosed decision back into an undisclosed one.
 *
 * So the claims that name a value are checked against the value.
 */
class ClientDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private function variations(): string
    {
        return file_get_contents(base_path('docs/VARIATIONS.md'));
    }

    public function test_the_variation_note_exists_and_covers_every_disclosed_item(): void
    {
        $document = $this->variations();

        foreach ([
            'Laravel 13.30.1', 'MariaDB 10.11', 'ElasticSearch',
            'Chart.js', 'Leaflet', 'WCAG 2.1', 'sentence case',
            'linking table', 'delegate', 'onboarding',
        ] as $item) {
            $this->assertStringContainsStringIgnoringCase($item, $document, $item.' is not covered.');
        }
    }

    public function test_the_colours_it_quotes_are_the_colours_that_shipped(): void
    {
        $tokens = file_get_contents(public_path('css/tokens.css'));
        $document = $this->variations();

        // A note that quotes a value the platform no longer uses is worse
        // than no note.
        preg_match('/--c-border:(#[0-9A-Fa-f]{6})/', $tokens, $border);
        preg_match('/--c-navy-edge:(#[0-9A-Fa-f]{6})/', $tokens, $edge);

        $this->assertStringContainsString($border[1], $document);
        $this->assertStringContainsString($edge[1], $document);
    }

    public function test_it_is_right_that_leaflet_is_still_in_use(): void
    {
        // The note tells CRASA that half the stack row is satisfied as
        // written. That has to stay true.
        $this->assertFileExists(public_path('js/vendor/leaflet.js'));
        $this->assertStringContainsString(
            'js/map.js',
            file_get_contents(resource_path('views/pages/contact.blade.php'))
        );
    }

    public function test_it_is_written_for_a_regulator_rather_than_a_developer(): void
    {
        $document = $this->variations();

        // Words a regulatory expert should not have to look up. Each of
        // these appears in the platform's own code and none belongs here.
        foreach ([
            'polymorphic', 'middleware', 'Eloquent', 'Blade', 'migration',
            'global scope', 'trait', 'refactor',
        ] as $jargon) {
            $this->assertStringNotContainsStringIgnoringCase(
                $jargon,
                $document,
                'The variation note uses the word "'.$jargon.'", which is developer jargon.'
            );
        }
    }

    public function test_the_house_voice_holds_in_the_client_documents(): void
    {
        foreach (['docs/VARIATIONS.md', 'docs/CLIENT_REQUESTS.md', 'docs/SSO_REQUIREMENTS.md'] as $path) {
            if (! file_exists(base_path($path))) {
                continue;
            }

            $document = file_get_contents(base_path($path));

            foreach (['seamless', 'innovative', 'robust', 'cutting-edge', 'leverage', 'empower', 'unlock'] as $adjective) {
                $this->assertDoesNotMatchRegularExpression(
                    '/\b'.$adjective.'\b/i',
                    $document,
                    $path.' uses the marketing adjective "'.$adjective.'".'
                );
            }

            // Curly quotes are the clearest sign of copy pasted from a
            // generator, and they break in plain-text email.
            foreach (["\u{2018}", "\u{2019}", "\u{201C}", "\u{201D}"] as $curly) {
                $this->assertStringNotContainsString($curly, $document, $path.' contains a curly quote.');
            }
        }
    }

    public function test_every_option_offered_to_the_client_carries_a_recommendation(): void
    {
        $document = $this->variations();

        // Eleven variations, each with options. A list of options with no
        // recommendation asks the client to do the supplier's thinking.
        $sections = preg_split('/\n## [A-E]\d\./', $document);
        $withOptions = array_filter($sections, fn ($s) => str_contains($s, "CRASA's options"));

        $this->assertGreaterThanOrEqual(6, count($withOptions));

        foreach ($withOptions as $section) {
            $this->assertMatchesRegularExpression(
                '/\*\*Recommended|No alternative is proposed/',
                $section,
                'A variation offers options without a recommendation.'
            );
        }
    }

    /**
     * The pack must stay in step with the platform.
     *
     * Every block flagged as awaiting content has to appear in the document
     * CRASA is sent, or the Secretariat is asked for nine things and the
     * platform is waiting on ten.
     */
    public function test_the_client_pack_covers_every_placeholder_the_platform_carries(): void
    {
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(PublicPagesSeeder::class);
        $this->seed(LegalPagesSeeder::class);

        $pack = file_get_contents(base_path('docs/CLIENT_REQUESTS.md'));

        $subjects = [
            'about' => ['Governance', 'Executive Committee', 'Secretariat', 'Partnerships', 'strategic'],
            'committees' => ['Terms of reference'],
            'members' => ['Accession', 'consent'],
            'privacy' => ['Privacy notice'],
            'terms' => ['Terms of use'],
        ];

        $flagged = PageBlock::where('needs_content', true)
            ->with('page')
            ->get()
            ->pluck('page.slug')
            ->unique()
            ->values();

        foreach ($flagged as $slug) {
            $this->assertArrayHasKey($slug, $subjects, 'The page "'.$slug.'" has a placeholder the client pack does not mention.');

            foreach ($subjects[$slug] as $term) {
                $this->assertStringContainsStringIgnoringCase($term, $pack);
            }
        }
    }

    /** The imagery brief has to carry the four things a photographer needs. */
    public function test_the_pack_states_the_imagery_brief_in_full(): void
    {
        $pack = file_get_contents(base_path('docs/CLIENT_REQUESTS.md'));

        // Subject, orientation, size, and where the type sits. A brief
        // missing any one of these produces images that cannot be used.
        $this->assertStringContainsStringIgnoringCase('Landscape', $pack);
        $this->assertStringContainsStringIgnoringCase('3000 pixels', $pack);
        $this->assertStringContainsStringIgnoringCase('right-hand two thirds', $pack);
        $this->assertStringContainsStringIgnoringCase('AI-generated', $pack);
        $this->assertStringContainsStringIgnoringCase('Three', $pack);
    }

    public function test_the_pack_names_all_fourteen_authorities_for_confirmation(): void
    {
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(AuthoritySeeder::class);

        $pack = file_get_contents(base_path('docs/CLIENT_REQUESTS.md'));

        // Every authority currently flagged unverified must be in the table
        // CRASA is asked to correct.
        foreach (Authority::where('is_sample', true)->get() as $authority) {
            $this->assertStringContainsString(
                $authority->acronym,
                $pack,
                $authority->acronym.' is seeded as unverified but is not in the confirmation table.'
            );
        }
    }

    public function test_the_pack_points_at_the_sso_document_rather_than_repeating_it(): void
    {
        $pack = file_get_contents(base_path('docs/CLIENT_REQUESTS.md'));

        $this->assertStringContainsString('SSO_REQUIREMENTS.md', $pack);
        $this->assertFileExists(base_path('docs/SSO_REQUIREMENTS.md'));
    }
}

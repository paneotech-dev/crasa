<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageBlock;
use App\Models\User;
use App\Support\Locales;
use Database\Seeders\CountrySeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(PublicPagesSeeder::class);
    }

    public function test_the_three_composed_pages_render_in_every_locale(): void
    {
        foreach (['about', 'committees', 'members'] as $slug) {
            foreach (Locales::SUPPORTED as $locale) {
                $this->get("/{$locale}/{$slug}")->assertOk();
            }
        }
    }

    public function test_each_page_is_a_record_with_blocks_not_a_template(): void
    {
        foreach (['about', 'committees', 'members'] as $slug) {
            $page = Page::where('slug', $slug)->sole();

            $this->assertTrue($page->isPublished());
            $this->assertGreaterThan(0, $page->blocks()->count());
        }
    }

    /**
     * Only what can be sourced is stated. CRASA's founding, its protocol,
     * its mandate and its membership are all in CLAUDE.md or the approved
     * screens.
     */
    public function test_about_states_only_what_can_be_sourced(): void
    {
        $html = $this->get('/en/about')->getContent();

        $this->assertStringContainsString('Specialised Agency', $html);
        $this->assertStringContainsString('1997', $html);
        $this->assertStringContainsString('Protocol on Transport, Communications and Meteorology', $html);
        $this->assertStringContainsString('fourteen member states', $html);
    }

    public function test_about_renders_the_founding_statement_in_all_three_languages(): void
    {
        $this->get('/fr/about')->assertSee('Protocole sur les transports');
        $this->get('/pt/about')->assertSee('Protocolo sobre Transportes');
    }

    /**
     * Where the build would be inventing, the block carries a visible note
     * and is flagged, rather than a plausible sentence about a regulator.
     */
    public function test_unsourceable_sections_are_flagged_rather_than_invented(): void
    {
        $flagged = PageBlock::where('needs_content', true)->get();

        $this->assertGreaterThanOrEqual(4, $flagged->count());

        foreach ($flagged as $block) {
            $this->assertNotNull($block->content_note, 'A flagged block must say what is needed.');
            $this->assertNotSame('', trim($block->content_note));
        }
    }

    public function test_a_flagged_block_is_not_shown_to_readers_in_any_language(): void
    {
        // Owner's instruction, 15 October 2026: no "being prepared" on the site.
        $this->get('/en/about')->assertDontSee('This section is being prepared');
        $this->get('/fr/about')->assertDontSee('Cette section est en préparation');
        $this->get('/pt/about')->assertDontSee('Esta secção está a ser preparada');

        // The back office still sees it in preview, with what it needs.
        $this->actingAs(User::factory()->editor()->create())
            ->get('/en/about')->assertSee('This section is being prepared');
    }

    public function test_no_committee_mandate_is_asserted(): void
    {
        $html = $this->get('/en/committees')->getContent();

        // The six names come from the approved screens. Their terms of
        // reference do not, so none is stated: the unwritten section is left
        // out rather than shown as "being prepared" (15 October 2026).
        $this->assertStringNotContainsString('This section is being prepared', $html);
        $this->assertStringNotContainsString('Terms of reference', $html);
    }

    public function test_the_members_page_lists_all_fourteen_states(): void
    {
        // Screen 08's directory, not the card grid it used to render. The
        // page shows the fourteen twice on purpose -- once on the map and
        // once in the list beside it -- so this counts the list.
        $html = $this->get('/en/members')->getContent();

        $this->assertSame(14, substr_count($html, 'b-directory__row'));
        $this->assertSame(14, substr_count($html, 'b-directory__link'));
    }

    public function test_the_placeholder_report_lists_every_flagged_block(): void
    {
        $this->artisan('content:placeholders')
            ->expectsOutputToContain('Governance')
            ->assertSuccessful();
    }
}

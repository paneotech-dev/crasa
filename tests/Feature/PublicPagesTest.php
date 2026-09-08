<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageBlock;
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

    public function test_a_flagged_block_says_so_on_the_page_in_every_language(): void
    {
        $this->get('/en/about')->assertSee('This section is being prepared');
        $this->get('/fr/about')->assertSee('Cette section est en préparation');
        $this->get('/pt/about')->assertSee('Esta secção está a ser preparada');
    }

    public function test_no_committee_mandate_is_asserted(): void
    {
        $html = $this->get('/en/committees')->getContent();

        // The six names come from the approved screens. Their terms of
        // reference do not, so none is stated.
        $this->assertStringContainsString('This section is being prepared', $html);
    }

    public function test_the_members_page_lists_all_fourteen_states(): void
    {
        $html = $this->get('/en/members')->getContent();

        $this->assertSame(14, substr_count($html, 'b-member__name'));
    }

    public function test_the_placeholder_report_lists_every_flagged_block(): void
    {
        $this->artisan('content:placeholders')
            ->expectsOutputToContain('Governance')
            ->assertSuccessful();
    }
}

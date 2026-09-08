<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsComposedPages;
use Tests\TestCase;

class PublicChromeTest extends TestCase
{
    use RefreshDatabase;
    use SeedsComposedPages;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedComposedPages();
    }

    public function test_the_header_carries_the_identity_navigation_and_extranet_action(): void
    {
        $response = $this->get('/en');

        $response->assertSee("Communications Regulators' Association of Southern Africa");
        $response->assertSee('aria-label="Primary"', false);
        $response->assertSee('Extranet');
        $response->assertSee('href="'.url('/en/extranet').'"', false);
    }

    public function test_the_nine_primary_items_are_all_present(): void
    {
        $response = $this->get('/en');

        foreach (['Home', 'About CRASA', 'Committees', 'Members', 'Publications', 'Newsroom', 'Events', 'Comparator', 'Contact'] as $label) {
            $response->assertSee($label);
        }
    }

    public function test_the_current_page_is_marked_in_the_navigation(): void
    {
        $this->get('/en')->assertSee('aria-current="page"', false);
    }

    public function test_the_footer_carries_the_secretariat_address_and_the_legal_row(): void
    {
        $response = $this->get('/en');

        $response->assertSee('P.O. Box 25793, Gaborone, Botswana');
        $response->assertSee('crasa@crasa.org');
        $response->assertSee('© '.now()->year.' CRASA. All rights reserved.', false);
        $response->assertSee('Accessibility statement');
    }

    public function test_every_page_opens_with_a_skip_link(): void
    {
        $this->get('/en')
            ->assertSee('class="c-skip" href="#content"', false)
            ->assertSee('Skip to content');
    }

    public function test_the_drawer_toggle_is_wired_for_assistive_technology(): void
    {
        $this->get('/en')
            ->assertSee('aria-controls="site-drawer"', false)
            ->assertSee('aria-expanded="false"', false);
    }

    public function test_the_token_layer_is_the_only_source_of_style(): void
    {
        $this->get('/en')
            ->assertSee('css/tokens.css', false)
            ->assertSee('css/chrome.css', false)
            ->assertDontSee('tailwind', false);
    }
}

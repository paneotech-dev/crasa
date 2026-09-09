<?php

namespace Tests\Feature;

use Database\Seeders\PublicPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The install prompt, screen 15 line 374, and the rule printed under it:
 * "Shown once, on the second visit, never as an interstitial over content."
 *
 * It shipped as a full-width navy band inside the document flow above the
 * footer. That is not the card the screen draws, and a band in the flow
 * pushes the footer down on every page for every reader whose browser
 * offers the prompt.
 */
class InstallPromptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PublicPagesSeeder::class);
    }

    private function css(): string
    {
        return file_get_contents(public_path('css/blocks.css'));
    }

    public function test_it_is_a_three_hundred_pixel_card(): void
    {
        // "width:300px;border:1px solid var(--c-sand);border-radius:
        //  var(--r-lg);overflow:hidden;background:var(--c-white);
        //  box-shadow:var(--e-2)"
        $css = $this->css();

        foreach ([
            'width: 300px',
            'border: 1px solid var(--c-sand)',
            'border-radius: var(--r-lg)',
            'overflow: hidden',
            'background: var(--c-white)',
            'box-shadow: var(--e-2)',
        ] as $declaration) {
            $this->assertMatchesRegularExpression(
                '/\.c-install__card \{[^}]*'.preg_quote($declaration, '/').'/',
                $css,
                "The card is missing: {$declaration}",
            );
        }
    }

    public function test_it_is_out_of_the_content_flow(): void
    {
        // A band above the footer moves the footer. The screen's card sits
        // over the page and moves nothing.
        $this->assertMatchesRegularExpression(
            '/\.c-install \{[^}]*position: fixed/',
            $this->css(),
        );

        $this->assertMatchesRegularExpression(
            '/\.c-install \{[^}]*inset-block-end/',
            $this->css(),
            'The card is fixed but not anchored to the bottom.',
        );
    }

    public function test_it_carries_the_source_bar_the_screen_draws(): void
    {
        // Line 375: the hostname on cream, above a sand rule. A card asking
        // to install something says where it comes from.
        $html = $this->get('/en/about')->assertOk()->getContent();

        $this->assertStringContainsString('c-install__source', $html);
        $this->assertMatchesRegularExpression(
            '/\.c-install__source \{[^}]*background: var\(--c-cream\)/',
            $this->css(),
        );
    }

    public function test_the_two_buttons_share_the_width_equally(): void
    {
        // "display:flex;gap:8px" with each button at "flex:1".
        $css = $this->css();

        $this->assertMatchesRegularExpression('/\.c-install__actions \{[^}]*gap: 8px/', $css);
        $this->assertMatchesRegularExpression('/\.c-install__actions \.c-btn \{[^}]*flex: 1/', $css);
    }

    public function test_it_is_served_hidden_on_every_page(): void
    {
        // The floor. Nothing reveals it but the script, on a second visit.
        foreach (['/en/about', '/en/contact', '/en/publications'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();

            $this->assertMatchesRegularExpression(
                '/<div class="c-install" data-install hidden>/',
                $html,
                "{$path} does not serve the prompt hidden.",
            );
        }
    }

    public function test_the_script_holds_it_to_the_second_visit_and_one_dismissal(): void
    {
        $js = file_get_contents(public_path('js/app.js'));

        $this->assertMatchesRegularExpression(
            '/if \(visits < 2 \|\| recall\(DISMISSED\) === .1.\) return;/',
            $js,
            'The prompt is not held to the second visit, or a dismissal does not stick.',
        );
    }

    public function test_it_never_covers_the_page_on_a_phone(): void
    {
        // 300px in a corner is most of a phone's width, and a card hanging
        // in the middle of one reads as a fault. Across the bottom instead.
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 639\.98px\) \{[^}]*\.c-install \{[^}]*inset-inline: var\(--s-4\)/s',
            $this->css(),
        );
    }
}

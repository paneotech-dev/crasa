<?php

namespace Tests\Feature;

use App\Services\ContentRenderer;
use App\Support\LegacyImages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The one place stored content becomes HTML.
 *
 * WHAT THIS IS FOR. Three render sites did three different things to the
 * same kind of value: one escaped its HTML and printed the escaped string,
 * so a regulator read "&lt;p&gt;" on the page; one printed scraped
 * third-party markup raw; one printed a page builder's shortcodes. These
 * pin the single answer that replaced all three.
 */
class ContentRendererTest extends TestCase
{
    use RefreshDatabase;

    private ContentRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
        $this->renderer = app(ContentRenderer::class);
    }

    private function render(?string $value): string
    {
        return $this->renderer->render($value)->toHtml();
    }

    /* What must survive ------------------------------------------------------ */

    public function test_paragraphs_render_as_paragraphs_rather_than_as_text(): void
    {
        $out = $this->render('<p>First.</p><p>Second.</p>');

        $this->assertStringContainsString('<p>First.</p>', $out);
        $this->assertStringNotContainsString('&lt;p&gt;', $out);
    }

    /**
     * A press release dates itself "the 31<sup>st</sup> of July". Losing the
     * superscript runs the date together.
     */
    public function test_superscript_and_subscript_survive(): void
    {
        $out = $this->render('<p>the 31<sup>st</sup> of July and H<sub>2</sub>O</p>');

        $this->assertStringContainsString('31<sup>st</sup>', $out);
        $this->assertStringContainsString('H<sub>2</sub>O', $out);
    }

    public function test_a_mailto_link_stays_clickable(): void
    {
        $out = $this->render('<p>Write to <a href="mailto:crasa@crasa.org">crasa@crasa.org</a>.</p>');

        $this->assertStringContainsString('href="mailto:crasa@crasa.org"', $out);
        // A mailto is not a window, so it gets no rel.
        $this->assertStringNotContainsString('rel="noopener', $out);
    }

    public function test_an_external_link_survives_and_carries_its_security_attributes(): void
    {
        $out = $this->render('<p><a href="https://www.itu.int/x">ITU</a></p>');

        $this->assertStringContainsString('href="https://www.itu.int/x"', $out);
        $this->assertStringContainsString('rel="noopener noreferrer"', $out);
    }

    public function test_a_same_page_fragment_and_a_local_path_survive(): void
    {
        $out = $this->render('<p><a href="#section">Jump</a> and <a href="/en/publications">library</a></p>');

        $this->assertStringContainsString('href="#section"', $out);
        $this->assertStringContainsString('href="/en/publications"', $out);
    }

    public function test_lists_headings_and_emphasis_survive(): void
    {
        $out = $this->render('<h3>Heading</h3><ul><li><strong>One</strong></li><li><em>Two</em></li></ul>');

        foreach (['<h3>Heading</h3>', '<ul>', '<li>', '<strong>One</strong>', '<em>Two</em>'] as $fragment) {
            $this->assertStringContainsString($fragment, $out);
        }
    }

    /* What must go ----------------------------------------------------------- */

    public function test_a_script_is_removed_with_its_contents(): void
    {
        $out = $this->render('<p>Before</p><script>alert(1)</script><p>After</p>');

        $this->assertStringNotContainsString('script', $out);
        // Unwrapping it would have printed its source as text, which is the
        // fault this whole class exists to prevent.
        $this->assertStringNotContainsString('alert(1)', $out);
        $this->assertStringContainsString('Before', $out);
        $this->assertStringContainsString('After', $out);
    }

    public function test_iframes_objects_and_embeds_are_removed(): void
    {
        $out = $this->render('<p>Text</p><iframe src="https://x"></iframe><object data="x"></object><embed src="x">');

        foreach (['iframe', 'object', 'embed'] as $tag) {
            $this->assertStringNotContainsString('<'.$tag, $out);
        }

        $this->assertStringContainsString('Text', $out);
    }

    public function test_a_javascript_url_loses_its_link_and_keeps_its_words(): void
    {
        $out = $this->render('<p><a href="javascript:alert(1)">Click me</a></p>');

        $this->assertStringNotContainsString('javascript:', $out);
        $this->assertStringNotContainsString('<a', $out);
        $this->assertStringContainsString('Click me', $out);
    }

    public function test_event_handler_attributes_are_removed(): void
    {
        $out = $this->render('<p onclick="steal()" onmouseover="x">Text</p><img src="x" onerror="steal()">');

        foreach (['onclick', 'onerror', 'onmouseover', 'steal()'] as $bad) {
            $this->assertStringNotContainsString($bad, $out);
        }
    }

    public function test_layout_classes_and_inline_styles_do_not_come_across(): void
    {
        $out = $this->render('<figure class="w-richtext-align-center"><div style="max-width:15%"><p>Caption</p></div></figure>');

        $this->assertStringNotContainsString('w-richtext', $out);
        $this->assertStringNotContainsString('style=', $out);
        $this->assertStringNotContainsString('<div', $out);
        $this->assertStringContainsString('Caption', $out);
    }

    public function test_h1_is_demoted_because_the_page_already_has_one(): void
    {
        $out = $this->render('<h1>Not the page title</h1>');

        $this->assertStringNotContainsString('<h1', $out);
        $this->assertStringContainsString('<h2>Not the page title</h2>', $out);
    }

    /* Shortcodes ------------------------------------------------------------- */

    public function test_page_builder_shortcodes_go_and_their_text_stays(): void
    {
        $out = $this->render('[vc_row][vc_column width="1/2"][vc_column_text] Real agenda text [/vc_column_text][vc_empty_space height="31px"][/vc_column][/vc_row]');

        $this->assertStringNotContainsString('[vc_', $out);
        $this->assertStringNotContainsString('vc_column', $out);
        $this->assertStringContainsString('Real agenda text', $out);
    }

    /**
     * WordPress smart-quoted these on the way in, so the stored text reads
     * width="1/2" with a double prime rather than a quote.
     */
    public function test_a_shortcode_smart_quoted_by_wordpress_is_still_removed(): void
    {
        $out = $this->render('[vc_row][vc_column width="1/2'."\u{2033}".'][vc_column_text] Kept [/vc_column_text][/vc_column][/vc_row]');

        $this->assertStringNotContainsString('vc_', $out);
        $this->assertStringContainsString('Kept', $out);
    }

    /**
     * The most important thing this class does not do. Two press releases
     * carry a bracketed dateline and the legal pages carry bracketed notes
     * naming what CRASA still has to supply.
     */
    public function test_ordinary_square_brackets_are_left_exactly_alone(): void
    {
        foreach ([
            '[Gaborone, 2nd June 2026] The Association announced today.',
            '[CRASA to state how changes to this notice will be communicated.]',
            'Section [4] of the protocol.',
        ] as $value) {
            $out = $this->renderer->text($value);

            $this->assertStringContainsString(
                trim(explode(']', $value)[0]).']',
                $out,
                'Bracketed content that is not a shortcode was removed.'
            );
        }
    }

    /* Images ----------------------------------------------------------------- */

    public function test_an_image_with_a_local_copy_is_served_from_the_platform(): void
    {
        Storage::disk('public')->put('images/legacy/news/photo.jpg', 'bytes');
        Storage::disk('local')->put('legacy/assets.json', json_encode([
            'images' => ['https://cdn.prod.website-files.com/x/abc_photo.jpg' => ['path' => 'images/legacy/news/photo.jpg']],
        ]));

        $out = app(ContentRenderer::class)->render('<p><img src="https://cdn.prod.website-files.com/x/abc_photo.jpg" alt=""></p>')->toHtml();

        $this->assertStringContainsString('images/legacy/news/photo.jpg', $out);
        $this->assertStringNotContainsString('cdn.prod.website-files.com', $out);
        $this->assertStringContainsString('loading="lazy"', $out);
    }

    /** No local copy means no image. The platform does not hot-link the old CDN. */
    public function test_an_image_with_no_local_copy_is_dropped_rather_than_hot_linked(): void
    {
        Storage::disk('local')->put('legacy/assets.json', json_encode(['images' => []]));

        $out = app(ContentRenderer::class)->render('<p>Text<img src="https://cdn.prod.website-files.com/x/missing.jpg"></p>')->toHtml();

        $this->assertStringNotContainsString('<img', $out);
        $this->assertStringNotContainsString('cdn.prod.website-files.com', $out);
        $this->assertStringContainsString('Text', $out);
    }

    /* Shape ------------------------------------------------------------------ */

    public function test_plain_text_keeps_its_line_breaks_and_is_escaped(): void
    {
        $out = $this->render("First line\nSecond line & third");

        $this->assertStringContainsString('<br', $out);
        $this->assertStringContainsString('&amp;', $out);
        $this->assertStringNotContainsString(' & ', $out);
    }

    public function test_empty_content_stays_empty(): void
    {
        foreach ([null, '', '   ', "\n\n"] as $value) {
            $this->assertSame('', $this->render($value));
        }
    }

    public function test_a_shortcode_only_value_renders_as_nothing(): void
    {
        $this->assertSame('', $this->render('[vc_row][vc_column][/vc_column][/vc_row]'));
    }

    public function test_malformed_markup_does_not_throw_and_does_not_leak_tags(): void
    {
        foreach ([
            '<p>Unclosed paragraph',
            '<div><p>Crossed</div></p>',
            '<p>Text</p></div></div>',
            '<<>><p>Odd</p>',
            str_repeat('<b>', 40).'deep'.str_repeat('</b>', 40),
        ] as $value) {
            $out = $this->render($value);

            $this->assertStringNotContainsString('&lt;p&gt;', $out);
            $this->assertStringNotContainsString('&lt;div', $out);
        }
    }

    public function test_zero_width_only_paragraphs_are_dropped(): void
    {
        $out = $this->render('<p>Real</p><p>'."\u{200D}".'</p><p>&nbsp;</p>');

        $this->assertSame(1, substr_count($out, '<p>'), 'Webflow\'s empty trailing paragraphs render as blank gaps.');
        $this->assertStringContainsString('Real', $out);
    }

    /** Rendering the output again produces the same output. */
    public function test_it_is_idempotent(): void
    {
        foreach ([
            '<p>the 31<sup>st</sup> of July, <a href="https://x.test">link</a></p>',
            '[vc_row][vc_column_text] Text [/vc_column_text][/vc_row]',
            'Plain text with a line.',
            '<figure class="w-richtext-align-center"><div><p>Caption</p></div></figure>',
        ] as $value) {
            $once = $this->render($value);
            $twice = $this->render($once);

            $this->assertSame($once, $twice, 'Rendering twice changed the result.');
        }
    }

    public function test_the_text_helper_returns_readable_text_with_no_markup(): void
    {
        $text = $this->renderer->text('<p>the 31<sup>st</sup> of July</p>[vc_empty_space]');

        $this->assertStringNotContainsString('<', $text);
        $this->assertStringNotContainsString('[vc_', $text);
        $this->assertStringContainsString('31st of July', $text);
    }
}

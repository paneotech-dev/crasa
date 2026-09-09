<?php

namespace Tests\Feature;

use App\Support\Locales;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\HomepageSeeder;
use Database\Seeders\IndicatorSeeder;
use Database\Seeders\IndicatorValueSeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Form controls: the size they render at, and the label that names them.
 *
 * Every select in the members section rendered about 200px tall.
 * .c-map-region__select carried "flex: 1 1 260px", written when the select
 * sat directly in a row container. It was later wrapped in a column-flex
 * field so its label could sit above it, and in a column the main axis is
 * vertical: 260px stopped being a width and became a height, and flex-grow
 * took it the rest of the way.
 *
 * A rendered height cannot be measured here. What can be held is the input
 * to it: no rule that applies to a select may give it a flex basis in
 * pixels, and the component itself refuses to grow.
 */
class FormControlSizingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(PublicPagesSeeder::class);
        $this->seed(HomepageSeeder::class);
        $this->seed(CommitteeSeeder::class);
        $this->seed(IndicatorSeeder::class);
        $this->seed(IndicatorValueSeeder::class);
    }

    public function test_the_select_component_never_grows_or_takes_a_basis(): void
    {
        $css = (string) file_get_contents(public_path('css/base.css'));

        $this->assertMatchesRegularExpression(
            '/\.c-select \{[^}]*flex: 0 0 auto/s',
            $css,
            'The select component can be stretched by whatever container it lands in.',
        );
    }

    public function test_no_rule_gives_a_select_a_pixel_flex_basis(): void
    {
        // The exact shape of the defect. A basis in pixels is a height the
        // moment the container is a column, and which axis a container uses
        // is not visible from the rule that sets the basis.
        foreach (['blocks.css', 'base.css', 'chrome.css', 'comparator.css'] as $file) {
            $css = (string) preg_replace(
                '#/\*.*?\*/#s',
                '',
                (string) file_get_contents(public_path('css/'.$file)),
            );

            preg_match_all('/([^{}]*select[^{}]*)\{([^{}]*)\}/i', $css, $rules, PREG_SET_ORDER);

            foreach ($rules as $rule) {
                $this->assertDoesNotMatchRegularExpression(
                    '/(^|;)\s*flex(-basis)?\s*:[^;]*\d+px/',
                    $rule[2],
                    trim($rule[1])." in {$file} gives a select a flex basis in pixels. "
                    .'In a column container that is its height.',
                );
            }
        }
    }

    public function test_every_control_on_a_public_form_has_a_label_with_words_in_it(): void
    {
        foreach (array_keys(Locales::all()) as $locale) {
            foreach (['/contact', '/members', '/comparator', '/search', ''] as $path) {
                $html = $this->get("/{$locale}{$path}")->assertOk()->getContent();

                // Every label's text, by the id it points at.
                preg_match_all('#<label[^>]*for="([^"]+)"[^>]*>(.*?)</label>#s', $html, $labels, PREG_SET_ORDER);

                $named = [];

                foreach ($labels as $label) {
                    $named[$label[1]] = trim(strip_tags($label[2]));
                }

                preg_match_all('/<(input|select|textarea)\b[^>]*>/', $html, $controls);

                foreach ($controls[0] as $control) {
                    if (preg_match('/type="(hidden|submit|button)"/', $control)) {
                        continue;
                    }

                    // aria-label is a legitimate accessible name of its own.
                    if (preg_match('/aria-label="([^"]+)"/', $control, $aria) && trim($aria[1]) !== '') {
                        continue;
                    }

                    preg_match('/\bid="([^"]+)"/', $control, $id);

                    $this->assertNotEmpty(
                        $id,
                        "/{$locale}{$path}: a control has neither an id nor an aria-label, so nothing "
                        ."can name it. {$control}",
                    );

                    $this->assertArrayHasKey(
                        $id[1],
                        $named,
                        "/{$locale}{$path}: no label points at #{$id[1]}.",
                    );

                    $this->assertNotSame(
                        '',
                        $named[$id[1]],
                        "/{$locale}{$path}: the label for #{$id[1]} is empty, so a screen reader "
                        .'announces the control and not what it is for.',
                    );
                }
            }
        }
    }

    public function test_a_picture_fills_the_box_it_is_given(): void
    {
        // <picture> is display:inline, so height:100% on the <img> resolves
        // to auto and object-fit never engages: the image sits at its
        // intrinsic size, centred, with the box showing round it. Found on
        // the hero and fixed there; the card and the band had the same
        // markup and had never been given the same rule.
        $css = (string) file_get_contents(public_path('css/blocks.css'))
            .(string) file_get_contents(public_path('css/comparator.css'));

        foreach ([
            'b-hero__media',
            'b-card__media',
            'c-band__media',
            'c-cmp__masthead-media',
        ] as $box) {
            $this->assertMatchesRegularExpression(
                '/\.'.preg_quote($box, '/').' picture \{[^}]*display: block/s',
                $css,
                ".{$box} lets its picture stay inline, so the image will not fill the box.",
            );
        }
    }
}

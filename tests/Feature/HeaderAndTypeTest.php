<?php

namespace Tests\Feature;

use Database\Seeders\PublicPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The header, against screen 02 line 70, and the type scale, against screen
 * 01 section 02.
 *
 * Both were checked in the polish run and neither needed changing. These
 * hold them, because "already correct" is only useful if it stays that way.
 */
class HeaderAndTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PublicPagesSeeder::class);
    }

    public function test_the_icon_button_meets_the_target_size(): void
    {
        // Screen 01 draws it at 44px square. Screen 02 draws 40. The larger
        // is both the newer drawing and the accessible one.
        $css = file_get_contents(public_path('css/chrome.css'));

        $this->assertMatchesRegularExpression('/\.c-iconbutton \{[^}]*width: 44px/', $css);
        $this->assertMatchesRegularExpression('/\.c-iconbutton \{[^}]*height: 44px/', $css);
    }

    public function test_the_type_scale_is_the_one_screen_01_draws(): void
    {
        $css = file_get_contents(public_path('css/tokens.css'));

        // Screen 01 section 02, each size with the line height and weight it
        // is set at there.
        $scale = [
            'display' => ['64px', '1.05', '600'],
            'h1' => ['44px', '1.12', '600'],
            'h2' => ['34px', '1.2', '600'],
            'h3' => ['24px', '1.3', '600'],
            'lead' => ['21px', '1.55', '400'],
            'body' => ['17px', '1.65', '400'],
        ];

        foreach ($scale as $name => [$size, $line, $weight]) {
            $this->assertStringContainsString("--t-{$name}-size:{$size}", $css, "{$name} is not {$size}.");
            $this->assertStringContainsString("--t-{$name}-line:{$line}", $css);
            $this->assertStringContainsString("--t-{$name}-weight:{$weight}", $css);
        }
    }

    public function test_running_text_is_held_to_the_reading_width(): void
    {
        // --w-reading is 1240 against --w-page at 1440. An article set to
        // the full page width runs past a comfortable measure.
        $css = file_get_contents(public_path('css/blocks.css'))
            .file_get_contents(public_path('css/base.css'));

        $this->assertStringContainsString('max-width: var(--w-reading)', $css);

        foreach (['/en/about'] as $path) {
            $this->assertMatchesRegularExpression(
                '/(b__inner--reading|c-page--reading)/',
                $this->get($path)->getContent(),
                "{$path} sets no reading width.",
            );
        }
    }
}

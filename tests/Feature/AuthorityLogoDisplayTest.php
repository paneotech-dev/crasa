<?php

namespace Tests\Feature;

use App\Enums\BlockType;
use App\Models\Country;
use App\Models\Page;
use App\Models\PageBlock;
use Database\Seeders\AuthoritySeeder;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Database\Seeders\SiteSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The regulator's own mark on the public surfaces.
 *
 * THE ALT RULES ARE THE POINT, and they differ by surface because the
 * visible text differs. On the detail page and the card the authority's
 * name is already on screen beside the mark, so a described image would be
 * the same name read twice. In the directory row the visible text is the
 * acronym, so the full name is something the image adds.
 *
 * And a member state with no logo has to get exactly the page it got
 * before, because thirteen of the fourteen will be in that state on any day
 * a new one is uploaded.
 */
class AuthorityLogoDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(AuthoritySeeder::class);
        $this->seed(CommitteeSeeder::class);
        $this->seed(PublicPagesSeeder::class);
        $this->seed(SiteSettingSeeder::class);
    }

    private function giveBotswanaALogo(): string
    {
        $path = 'images/authorities/bw.png';
        Storage::disk('public')->put($path, 'a-mark');

        $botswana = Country::where('iso_code', 'bw')->firstOrFail();
        $botswana->authority->forceFill(['logo_path' => $path])->save();

        return Storage::disk('public')->url($path);
    }

    public function test_the_detail_page_shows_the_mark_with_an_empty_alt(): void
    {
        $url = $this->giveBotswanaALogo();

        $html = $this->get('/en/members/bw')->assertOk()->getContent();

        $this->assertStringContainsString('class="c-member__logo"', $html);
        $this->assertStringContainsString('src="'.$url.'"', $html);

        // Empty alt: the authority's name is the line directly below it.
        $this->assertMatchesRegularExpression(
            '/<img src="'.preg_quote($url, '/').'"\s+alt=""/',
            $html
        );

        // And the name is still there, once.
        $this->assertSame(
            1,
            substr_count($html, '<p class="u-lead">Botswana Communications Regulatory Authority</p>')
        );
    }

    public function test_the_detail_page_is_unchanged_for_a_member_state_with_no_logo(): void
    {
        $html = $this->get('/en/members/mw')->assertOk()->getContent();

        $this->assertStringNotContainsString('c-member__logo', $html);
        // The flag and the name are exactly where they were.
        $this->assertStringContainsString('c-member__heading', $html);
        $this->assertStringContainsString('Malawi Communications Regulatory Authority', $html);
    }

    public function test_the_directory_row_names_the_authority_in_its_alt(): void
    {
        $url = $this->giveBotswanaALogo();

        $html = $this->get('/en/members')->assertOk()->getContent();

        // The directory is a grid of cards since C14 (29 October 2026).
        $this->assertStringContainsString('class="cp-member__logo"', $html);
        $this->assertStringContainsString(
            'alt="Botswana Communications Regulatory Authority"',
            $html,
            'The directory row shows the acronym, so the mark has to carry the full name.'
        );
        $this->assertStringContainsString($url, $html);
    }

    public function test_the_card_shows_the_mark_with_an_empty_alt(): void
    {
        $url = $this->giveBotswanaALogo();

        // The card grid, which is the member_grid block without the
        // directory setting the members page turns on.
        $page = Page::where('slug', 'members')->firstOrFail();
        $page->blocks()
            ->where('block_type', BlockType::MemberGrid->value)
            ->first()
            ->update(['settings' => ['show_map' => false, 'filter' => [], 'directory' => false]]);

        $html = $this->get('/en/members')->assertOk()->getContent();

        $this->assertStringContainsString('class="b-member__logo"', $html);
        $this->assertMatchesRegularExpression(
            '/<img src="'.preg_quote($url, '/').'"\s+alt=""/',
            $html
        );
    }

    /** The 160px tiles keep the flag: a 591x244 wordmark in a 34px slot is unreadable. */
    public function test_the_small_tiles_are_left_alone(): void
    {
        $this->giveBotswanaALogo();

        $page = Page::where('slug', 'members')->firstOrFail();
        $block = $page->blocks()->where('block_type', BlockType::MemberGrid->value)->first();
        $block->update(['settings' => ['show_map' => true, 'filter' => [], 'directory' => false, 'summary' => true]]);

        $html = $this->get('/en/members')->assertOk()->getContent();

        if (str_contains($html, 'b-members__tile-mark')) {
            $tiles = substr($html, strpos($html, 'b-members__tiles'));
            $tiles = substr($tiles, 0, strpos($tiles, '</ul>') ?: null);

            $this->assertStringNotContainsString('b-member__logo', $tiles);
            $this->assertStringNotContainsString('b-directory__logo', $tiles);
            $this->assertStringNotContainsString('cp-member__logo', $tiles);
        }
    }

    public function test_the_listing_holds_up_when_no_member_state_has_a_logo(): void
    {
        $this->get('/en/members')
            ->assertOk()
            ->assertSee('Botswana')
            ->assertDontSee('b-member__logo')
            ->assertDontSee('b-directory__logo')
            ->assertDontSee('cp-member__logo');
    }

    public function test_every_logo_rule_uses_tokens_and_no_raw_colour(): void
    {
        $css = file_get_contents(public_path('css/blocks.css'));

        foreach (['.b-member__logo', '.c-member__logo', '.b-directory__logo'] as $selector) {
            $this->assertStringContainsString($selector, $css, $selector.' has no styles behind it.');
        }

        preg_match_all('/\.(?:b-member__logo|c-member__logo|b-directory__logo)[^{]*\{([^}]*)\}/', $css, $rules);

        foreach ($rules[1] as $body) {
            $this->assertDoesNotMatchRegularExpression(
                '/#[0-9a-f]{3,8}\b|rgba?\(/i',
                $body,
                'A logo rule carries a raw colour. Tokens only.'
            );
        }
    }
}

<?php

namespace Tests\Feature;

use App\Models\NewsItem;
use App\Models\Page;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Evening pass: the rows of FIXES_FOR_STREAMS.md still open on d5565ed. */
class EveningAccessibilityRowsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['crasa.two_factor_required' => false]);
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
    }

    public function test_row_5_the_title_separator_is_a_bar_not_an_em_dash(): void
    {
        $html = $this->get('/en/contact')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('~<title>[^<]+ \| CRASA</title>~u', $html);
        $this->assertStringNotContainsString("\u{2014} CRASA</title>", $html);

        foreach (['layouts/public.blade.php', 'layouts/shell.blade.php'] as $file) {
            $this->assertStringNotContainsString("\u{2014}", file_get_contents(resource_path('views/'.$file)), $file);
        }

        $admin = User::factory()->administrator()->create();
        $this->assertMatchesRegularExpression('~<title>[^<]+ \| [^<]+</title>~u', $this->actingAs($admin)->get('/en/admin')->getContent());
    }

    public function test_row_6_the_head_snippet_applies_every_remembered_display_option_before_paint(): void
    {
        $html = $this->get('/en')->getContent();
        $this->assertStringContainsString("['contrast','underline','spacing','dyslexia','pause'].forEach", $html);
    }

    public function test_row_10_the_current_page_number_carries_its_words_in_text_not_an_aria_label_on_a_span(): void
    {
        NewsItem::factory()->count(30)->create(['status' => 'published', 'published_at' => now()->subDay()]);

        $html = $this->get('/en/newsroom')->assertOk()->getContent();
        $this->assertStringNotContainsString('<span aria-current="page" aria-label=', $html);
        $this->assertStringContainsString('<span aria-current="page"><span class="c-sr">Page 1, current page</span><span aria-hidden="true">1</span></span>', $html);
    }

    public function test_row_15_the_accessibility_page_record_says_it_is_not_the_public_statement(): void
    {
        Page::create(['slug' => 'accessibility', 'template' => 'standard', 'status' => \App\Enums\PageStatus::Published]);
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin)->get('/en/admin/pages/accessibility')->assertOk()->assertSee('data-statement-note', false);
    }

    public function test_row_19_date_fields_show_a_focus_ring_and_row_20_the_folders_title_is_not_the_section_name(): void
    {
        $this->assertStringContainsString('.a-input[type="date"]:focus-within', file_get_contents(public_path('css/admin-workflow.css')));
        $this->assertStringContainsString("@section('title', __('extranet.folders'))", file_get_contents(resource_path('views/pages/extranet/index.blade.php')));
    }

    public function test_back_office_table_frames_contain_their_visually_hidden_text_at_390(): void
    {
        $this->assertStringContainsString('.a-tablewrap { position: relative; }', file_get_contents(public_path('css/admin-workflow.css')));
    }
}

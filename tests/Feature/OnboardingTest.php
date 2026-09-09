<?php

namespace Tests\Feature;

use App\Enums\BlockType;
use App\Models\User;
use App\Support\Onboarding;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Onboarding.
 *
 * No approved screen covers this, so what it must not be matters as much as
 * what it is. It is not a modal overlay walkthrough: that pattern traps
 * focus, is nearly impossible to operate with a screen reader, and cannot
 * be re-read once dismissed. WCAG 2.1 AA is a contractual acceptance
 * criterion here, not an aspiration.
 */
class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);

        $this->administrator = User::factory()->administrator()->create();
    }

    public function test_a_new_account_meets_the_introduction_on_the_dashboard(): void
    {
        $this->assertFalse($this->administrator->hasDismissed(Onboarding::WELCOME));

        $this->actingAs($this->administrator)
            ->get('/en/admin')
            ->assertOk()
            ->assertSee(__('help.welcome_body'))
            ->assertSee(__('help.step_content_title'));
    }

    public function test_the_introduction_is_a_panel_on_the_page_and_not_an_overlay(): void
    {
        $html = $this->actingAs($this->administrator)->get('/en/admin')->getContent();

        // A panel in the flow of the document, inside the dashboard's own
        // grid. Nothing that dims the page or traps focus.
        $this->assertStringContainsString('a-intro', $html);
        $this->assertStringNotContainsString('role="dialog"', $html);
        $this->assertStringNotContainsString('aria-modal', $html);
    }

    public function test_dismissing_is_per_account_and_survives_signing_in_elsewhere(): void
    {
        $this->actingAs($this->administrator)
            ->from('/en/admin')
            ->post('/en/admin/help/dismiss', ['hint' => Onboarding::WELCOME])
            ->assertRedirect();

        // Recorded on the account, not in a browser: one person who has
        // read it once, whichever machine they sign in from next.
        $this->assertTrue($this->administrator->fresh()->hasDismissed(Onboarding::WELCOME));

        $this->actingAs($this->administrator->fresh())
            ->get('/en/admin')
            ->assertOk()
            ->assertDontSee(__('help.welcome_body'));
    }

    public function test_nothing_is_lost_by_dismissing_it(): void
    {
        $this->administrator->dismiss(Onboarding::WELCOME);

        $this->actingAs($this->administrator->fresh())
            ->get('/en/admin/help')
            ->assertOk()
            ->assertSee(__('help.step_content_title'))
            ->assertSee(__('help.step_audit_title'));
    }

    public function test_the_introduction_can_be_brought_back(): void
    {
        $this->administrator->dismiss(Onboarding::WELCOME);

        $this->actingAs($this->administrator->fresh())
            ->from('/en/admin/help')
            ->post('/en/admin/help/restore')
            ->assertRedirect();

        $this->assertFalse($this->administrator->fresh()->hasDismissed(Onboarding::WELCOME));
    }

    public function test_only_a_known_hint_can_be_dismissed(): void
    {
        $this->actingAs($this->administrator)
            ->from('/en/admin')
            ->post('/en/admin/help/dismiss', ['hint' => 'everything'])
            ->assertSessionHasErrors('hint');

        $this->assertSame([], $this->administrator->fresh()->dismissed_hints ?? []);
    }

    public function test_a_step_this_account_cannot_open_is_not_offered(): void
    {
        $editor = User::factory()->editor()->create();

        $html = $this->actingAs($editor)->get('/en/admin/help')->assertOk()->getContent();

        // An introduction that spends a paragraph on a screen the reader
        // will be refused is worse than no introduction.
        $this->assertStringContainsString(__('help.step_content_title'), $html);
        $this->assertStringNotContainsString(__('help.step_users_title'), $html);
    }

    public function test_every_block_type_is_explained_on_the_help_page(): void
    {
        $html = $this->actingAs($this->administrator)->get('/en/admin/help')->assertOk()->getContent();

        foreach (BlockType::cases() as $type) {
            $this->assertStringContainsString(e(__('blocks.explain_'.$type->value)), $html);
        }
    }

    public function test_the_rules_the_platform_holds_to_are_written_down(): void
    {
        $html = $this->actingAs($this->administrator)->get('/en/admin/help')->assertOk()->getContent();

        foreach (['languages', 'publishing', 'alt_text', 'deleting', 'audit', 'placeholders'] as $rule) {
            $this->assertStringContainsString(e(__('help.rule_'.$rule.'_title')), $html);
            $this->assertNotSame('help.rule_'.$rule.'_body', __('help.rule_'.$rule.'_body'));
        }
    }

    public function test_the_help_page_exists_in_all_three_languages(): void
    {
        foreach (['en', 'fr', 'pt'] as $locale) {
            $this->actingAs($this->administrator)
                ->get("/{$locale}/admin/help")
                ->assertOk()
                ->assertSee(__('help.sections_title', [], $locale));
        }
    }

    public function test_inline_help_is_a_disclosure_and_not_a_hover_tooltip(): void
    {
        $html = (string) $this->blade(
            '<x-admin.help title="What is this">An explanation.</x-admin.help>'
        );

        // A tooltip does not exist for anyone on a touch screen, and does
        // not exist for a keyboard user unless it is rebuilt from scratch.
        $this->assertStringContainsString('<details', $html);
        $this->assertStringContainsString('<summary', $html);
        $this->assertStringNotContainsString('title="', $html);
    }

    public function test_a_viewer_reaches_no_part_of_the_help_for_a_back_office_they_cannot_enter(): void
    {
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer)->get('/en/admin/help')->assertForbidden();
        $this->actingAs($viewer)->post('/en/admin/help/dismiss', ['hint' => Onboarding::WELCOME])->assertForbidden();
    }
}

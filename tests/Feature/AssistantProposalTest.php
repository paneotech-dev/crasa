<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Country;
use App\Models\FeatureInterest;
use App\Models\User;
use App\Support\Locales;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A proposal, and the line it must not cross.
 *
 * CLAUDE.md reserves conversational features for a separate commercial
 * addendum and forbids building them in this codebase. Describing what one
 * would do, and counting who would use it, is not building one -- and the
 * difference is the whole of this file.
 *
 * HALF OF IT IS ABOUT WHAT DOES NOT EXIST. No input, no textarea, no
 * contenteditable, no request to anybody, no vendor named, no model named,
 * no date given. Adding any of those is a failing test rather than a quiet
 * commit.
 */
class AssistantProposalTest extends TestCase
{
    use RefreshDatabase;

    private User $rep;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(CommitteeSeeder::class);

        $this->rep = User::factory()->memberStateRep(Country::where('iso_code', 'bw')->sole())->create();
    }

    private function dashboard(?User $user = null): string
    {
        return $this->actingAs($user ?? $this->rep)
            ->get('/en/extranet/dashboard')
            ->assertOk()
            ->getContent();
    }

    /* What it says ------------------------------------------------------- */

    public function test_the_card_and_the_panel_both_say_it_is_a_proposal(): void
    {
        $html = $this->dashboard();

        // Twice: once on the card, once in the panel. A proposal that says
        // so only on the card is one somebody quotes from the panel as a
        // commitment.
        $this->assertSame(2, substr_count($html, __('assistant.label')));
        $this->assertStringContainsString(__('assistant.title'), $html);
        $this->assertStringContainsString(__('assistant.framing'), $html);
    }

    public function test_the_panel_lists_five_capabilities_with_an_example_each(): void
    {
        $html = $this->dashboard();

        foreach (['compare', 'gap', 'draft', 'language', 'return'] as $key) {
            $this->assertStringContainsString(__('assistant.'.$key.'_title'), $html, $key.' has no title.');
            $this->assertStringContainsString(e(__('assistant.'.$key.'_example')), $html, $key.' has no example.');
        }

        // On the exact class attribute: __itemmark and __itemtitle both
        // contain the row class as a substring.
        $this->assertSame(5, substr_count($html, 'class="a-proposal__item"'));
    }

    public function test_the_panel_states_how_it_would_work_and_where_it_stands(): void
    {
        $html = $this->dashboard();

        $this->assertStringContainsString(e(__('assistant.how_sources')), $html);
        $this->assertStringContainsString(__('assistant.how_cites'), $html);
        $this->assertStringContainsString(__('assistant.how_clearance'), $html);
        $this->assertStringContainsString(__('assistant.status'), $html);
    }

    /** No date, no vendor, no model, no "coming soon". */
    public function test_it_promises_nothing(): void
    {
        foreach (Locales::SUPPORTED as $locale) {
            $strings = strtolower(implode(' ', require base_path("lang/{$locale}/assistant.php")));

            foreach ([
                'coming soon', 'bientôt', 'em breve',
                'openai', 'anthropic', 'gpt', 'claude', 'gemini', 'llama', 'copilot',
                '2026', '2027', 'q1', 'q2',
            ] as $forbidden) {
                $this->assertStringNotContainsString(
                    $forbidden,
                    $strings,
                    $locale.' promises or names something it must not: '.$forbidden
                );
            }
        }
    }

    public function test_it_reads_in_all_three_languages(): void
    {
        foreach (Locales::SUPPORTED as $locale) {
            $html = $this->actingAs($this->rep)
                ->get("/{$locale}/extranet/dashboard")
                ->assertOk()
                ->getContent();

            $this->assertStringContainsString(__('assistant.title', [], $locale), $html);
            $this->assertStringContainsString(__('assistant.label', [], $locale), $html);
            $this->assertStringContainsString(e(__('assistant.compare_example', [], $locale)), $html);
        }

        // Translated, not copied. Each locale's examples differ from the
        // English, which is what a mechanical placeholder would not.
        foreach (['fr', 'pt'] as $locale) {
            foreach (['compare_example', 'gap_example', 'status'] as $key) {
                $this->assertNotSame(
                    __('assistant.'.$key, [], 'en'),
                    __('assistant.'.$key, [], $locale),
                    $locale.'.'.$key.' is the English string.'
                );
            }
        }
    }

    /* THE LINE ----------------------------------------------------------- */

    /**
     * Nothing on this platform can be typed into on the subject of an
     * assistant, and nothing here asks anybody anything.
     */
    public function test_there_is_no_input_anywhere_in_the_proposal(): void
    {
        $source = File::get(resource_path('views/partials/assistant-proposal.blade.php'));

        // Comments first: this file explains at length that it builds no
        // such thing, and the word "input" appears in that explanation.
        $markup = preg_replace('/\{\{--.*?--\}\}/s', '', $source);

        $this->assertStringNotContainsString('<textarea', $markup);
        $this->assertStringNotContainsString('contenteditable', $markup);
        $this->assertStringNotContainsString('type="text"', $markup);
        $this->assertStringNotContainsString('type="search"', $markup);

        // The one input is the hidden field naming which proposal the
        // reader is voting on. It is not a field anybody types into.
        preg_match_all('/<input\b[^>]*>/', $markup, $inputs);

        foreach ($inputs[0] as $input) {
            $this->assertMatchesRegularExpression(
                '/type="hidden"|name="_token"/',
                $input,
                'The proposal carries a control a reader can type into: '.$input
            );
        }
    }

    /** And the rendered page carries none either. */
    public function test_the_rendered_panel_carries_no_field(): void
    {
        $html = $this->dashboard();

        $panel = substr($html, strpos($html, '<dialog id="assistant-panel"'));
        $panel = substr($panel, 0, strpos($panel, '</dialog>'));

        $this->assertStringNotContainsString('<textarea', $panel);
        $this->assertStringNotContainsString('type="text"', $panel);
        $this->assertStringNotContainsString('contenteditable', $panel);
    }

    /** No request leaves this platform. */
    public function test_nothing_here_calls_anywhere(): void
    {
        foreach ([
            resource_path('views/partials/assistant-proposal.blade.php'),
            app_path('Http/Controllers/FeatureInterestController.php'),
            public_path('js/modal.js'),
        ] as $path) {
            $source = File::get($path);

            foreach (['https://', 'fetch(', 'XMLHttpRequest', 'Http::', 'curl'] as $call) {
                $this->assertStringNotContainsString($call, $source, $path.' reaches outside the platform.');
            }
        }
    }

    /* Who sees it -------------------------------------------------------- */

    public function test_only_member_states_and_committee_members_see_it(): void
    {
        $this->assertStringContainsString(__('assistant.title'), $this->dashboard());

        $member = User::factory()->committeeMember(Country::where('iso_code', 'zm')->sole())->create();
        $this->assertStringContainsString(__('assistant.title'), $this->dashboard($member));

        // The Secretariat is not asked. A count that includes the people
        // proposing it is a count of the wrong thing.
        foreach ([
            User::factory()->administrator()->create(),
            User::factory()->editor()->create(),
        ] as $secretariat) {
            $html = $this->actingAs($secretariat)->get('/en/admin')->assertOk()->getContent();
            $this->assertStringNotContainsString(__('assistant.title'), $html);
        }
    }

    public function test_an_editor_cannot_record_interest(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->post('/en/extranet/interest', [
            'feature' => FeatureInterest::ASSISTANT,
        ])->assertForbidden();

        $this->assertSame(0, FeatureInterest::count());
    }

    /* The one thing it does ---------------------------------------------- */

    public function test_recording_interest_writes_one_row_and_one_audit_entry(): void
    {
        $this->actingAs($this->rep)->post('/en/extranet/interest', [
            'feature' => FeatureInterest::ASSISTANT,
        ])->assertRedirect();

        $this->assertSame(1, FeatureInterest::where('user_id', $this->rep->id)->count());

        $this->assertDatabaseHas('audit_log', [
            'auditable_type' => FeatureInterest::class,
            'action' => AuditLog::CREATED,
            'user_id' => $this->rep->id,
        ]);

        // A second click is not a second vote.
        $this->actingAs($this->rep)->post('/en/extranet/interest', [
            'feature' => FeatureInterest::ASSISTANT,
        ])->assertRedirect();

        $this->assertSame(1, FeatureInterest::count());
        $this->assertSame(1, AuditLog::where('auditable_type', FeatureInterest::class)->count());
    }

    public function test_the_button_becomes_an_acknowledgement(): void
    {
        $this->assertStringContainsString(__('assistant.interest'), $this->dashboard());

        FeatureInterest::create([
            'user_id' => $this->rep->id,
            'feature' => FeatureInterest::ASSISTANT,
            'created_at' => now(),
        ]);

        $html = $this->dashboard();

        $this->assertStringContainsString(__('assistant.noted'), $html);
        $this->assertStringNotContainsString(__('assistant.interest'), $html);
    }

    public function test_an_unknown_proposal_is_refused(): void
    {
        $this->actingAs($this->rep)->post('/en/extranet/interest', ['feature' => 'something-else'])
            ->assertSessionHasErrors('feature');

        $this->assertSame(0, FeatureInterest::count());
    }

    /* The floor ----------------------------------------------------------- */

    /**
     * With no script the dialog is an ordinary section of the page and the
     * button that opens it is not drawn. The focus trap and Escape are the
     * browser's, from showModal, rather than reimplemented.
     */
    public function test_the_panel_is_readable_with_no_script(): void
    {
        $css = File::get(public_path('css/admin.css'));

        $this->assertStringContainsString('html:not(.has-js) .a-modal {', $css);
        $this->assertMatchesRegularExpression(
            '/html:not\(\.has-js\) \[data-modal-open\][^{]*\{[^}]*display: none/s',
            $css
        );

        $js = File::get(public_path('js/modal.js'));
        $this->assertStringContainsString('showModal()', $js);
        $this->assertStringNotContainsString('keydown', $js);
    }
}

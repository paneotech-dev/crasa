<?php

namespace Tests\Feature;

use App\Enums\Clearance;
use App\Models\AuditLog;
use App\Models\Committee;
use App\Models\Country;
use App\Models\Meeting;
use App\Models\NewsItem;
use App\Models\Publication;
use App\Models\User;
use App\Support\Approvals;
use App\Support\ModeratorScope;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Item 1j: a moderator limited to member states and/or committees sees and
 * decides only items in that scope; an unscoped moderator is unchanged.
 */
class ModeratorScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $editor;

    private User $scoped;

    private User $unscoped;

    private Country $botswana;

    private Country $zambia;

    private Committee $spectrum;

    protected function setUp(): void
    {
        parent::setUp();
        config(['crasa.two_factor_required' => false]);
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        Notification::fake();

        $this->admin = User::factory()->administrator()->create();
        $this->editor = User::factory()->editor()->create();
        $this->scoped = User::factory()->moderator()->create();
        $this->unscoped = User::factory()->moderator()->create();
        $this->botswana = Country::where('iso_code', 'bw')->firstOrFail();
        $this->zambia = Country::where('iso_code', 'zm')->firstOrFail();
        $this->spectrum = Committee::factory()->create();

        ModeratorScope::set($this->scoped, [$this->botswana->id], [$this->spectrum->id], $this->admin);
    }

    /** @return array{0: \App\Models\Approval, 1: \App\Models\Approval, 2: \App\Models\Approval, 3: \App\Models\Approval} */
    private function fourItems(): array
    {
        $inCountry = Publication::factory()->create(['is_public' => false, 'external_url' => 'https://www.crasa.org/doc.pdf', 'country_id' => $this->botswana->id]);
        $outCountry = Publication::factory()->create(['is_public' => false, 'country_id' => $this->zambia->id]);
        $inCommittee = Meeting::factory()->create(['clearance' => Clearance::Member, 'committee_id' => $this->spectrum->id]);
        $regional = NewsItem::factory()->draft()->create();

        return [
            Approvals::submit($inCountry, $this->editor),
            Approvals::submit($outCountry, $this->editor),
            Approvals::submit($inCommittee, $this->editor),
            Approvals::submit($regional, $this->editor),
        ];
    }

    public function test_the_inbox_holds_only_items_in_the_moderators_scope(): void
    {
        [$inCountry, $outCountry, $inCommittee, $regional] = $this->fourItems();

        $ids = Approvals::awaitingFor($this->scoped)->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$inCountry->id, $inCommittee->id], $ids);
        $this->assertSame(2, Approvals::awaitingCountFor($this->scoped));

        $this->actingAs($this->scoped)->get('/en/admin/approvals')
            ->assertOk()
            ->assertSee('Your scope: '.$this->botswana->displayName().', '.$this->spectrum->displayName(), false)
            ->assertSee("/en/admin/approvals/{$inCountry->id}", false)
            ->assertDontSee("/en/admin/approvals/{$outCountry->id}\"", false)
            ->assertDontSee("/en/admin/approvals/{$regional->id}\"", false);
    }

    public function test_an_unscoped_moderator_sees_every_item_and_no_scope_line(): void
    {
        $this->fourItems();

        $this->assertSame(4, Approvals::awaitingCountFor($this->unscoped));
        $this->assertCount(4, Approvals::awaitingFor($this->unscoped));

        $this->actingAs($this->unscoped)->get('/en/admin/approvals')
            ->assertOk()->assertDontSee('data-moderation-scope', false);
    }

    public function test_approving_or_returning_outside_the_scope_is_refused_on_the_server(): void
    {
        [$inCountry, $outCountry, , $regional] = $this->fourItems();

        foreach ([$outCountry, $regional] as $approval) {
            $this->actingAs($this->scoped)->get("/en/admin/approvals/{$approval->id}")->assertForbidden();
            $this->actingAs($this->scoped)->put("/en/admin/approvals/{$approval->id}/approve", ['comment' => ''])->assertForbidden();
            $this->actingAs($this->scoped)->put("/en/admin/approvals/{$approval->id}/return", ['comment' => 'No'])->assertForbidden();
            $this->assertSame('in_review', $approval->fresh()->state);
            $this->assertFalse(Approvals::mayDecide($this->scoped, $approval));
        }

        $this->actingAs($this->scoped)->put("/en/admin/approvals/{$inCountry->id}/approve", ['comment' => ''])
            ->assertRedirect('/en/admin/approvals');
        $this->assertSame('approved', $inCountry->fresh()->state);
    }

    public function test_an_unscoped_moderator_still_approves_a_regional_item(): void
    {
        [, , , $regional] = $this->fourItems();

        $this->actingAs($this->unscoped)->put("/en/admin/approvals/{$regional->id}/approve", ['comment' => ''])
            ->assertRedirect('/en/admin/approvals');
        $this->assertSame('approved', $regional->fresh()->state);
    }

    public function test_the_scope_is_saved_from_the_edit_screen_and_audited(): void
    {
        $this->actingAs($this->admin)->get("/en/admin/users/{$this->unscoped->id}/edit")
            ->assertOk()->assertSee(__('m3_scope.rule'), false);

        $this->actingAs($this->admin)->put("/en/admin/users/{$this->unscoped->id}", [
            'name' => $this->unscoped->name,
            'email' => $this->unscoped->email,
            'role' => 'moderator',
            'scope_countries' => [$this->zambia->id],
            'scope_committees' => [$this->spectrum->id],
        ])->assertRedirect("/en/admin/users/{$this->unscoped->id}")->assertSessionHasNoErrors();

        $this->assertSame(['countries' => [$this->zambia->id], 'committees' => [$this->spectrum->id]], ModeratorScope::of($this->unscoped));

        $entry = AuditLog::where('auditable_type', $this->unscoped->getMorphClass())->where('auditable_id', $this->unscoped->id)
            ->where('user_id', $this->admin->id)->get()
            ->first(fn (AuditLog $row) => isset($row->changes['moderation_scope']));
        $this->assertNotNull($entry);
        $this->assertSame([$this->zambia->id], $entry->changes['moderation_scope']['to']['countries']);

        // Clearing every box makes the moderator unscoped again.
        $this->actingAs($this->admin)->put("/en/admin/users/{$this->unscoped->id}", [
            'name' => $this->unscoped->name,
            'email' => $this->unscoped->email,
            'role' => 'moderator',
        ])->assertSessionHasNoErrors();
        $this->assertFalse(ModeratorScope::isScoped($this->unscoped));
    }

    public function test_a_scope_naming_an_unknown_member_state_is_refused_and_others_may_not_set_one(): void
    {
        $this->actingAs($this->admin)->put("/en/admin/users/{$this->unscoped->id}", [
            'name' => $this->unscoped->name,
            'email' => $this->unscoped->email,
            'scope_countries' => [999999],
        ])->assertSessionHasErrors('scope_countries.0');

        $this->actingAs($this->scoped)->put("/en/admin/users/{$this->unscoped->id}", [
            'name' => $this->unscoped->name,
            'email' => $this->unscoped->email,
            'scope_countries' => [$this->zambia->id],
        ])->assertForbidden();
        $this->assertFalse(ModeratorScope::isScoped($this->unscoped));
    }
}

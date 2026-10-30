<?php

namespace Tests\Feature;

use App\Enums\PageStatus;
use App\Enums\UserStatus;
use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\DelegateRegistration;
use App\Models\Enquiry;
use App\Models\Meeting;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** crasa:prepare-production: the demonstration accounts and their workshop rows go, nothing else. */
class PrepareProductionCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $demoAdmin;

    private User $demoAdmin2;

    private User $demoEditor;

    private User $realAdmin;

    private User $realEditor;

    private Page $page;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://crasa.africa']);

        $this->demoAdmin = User::factory()->administrator()->create(['email' => 'admin@crasa.org', 'is_sample' => false]);
        $this->demoAdmin2 = User::factory()->administrator()->create(['email' => 'admin2@crasadev.invalid', 'is_sample' => true]);
        $this->demoEditor = User::factory()->editor()->create(['email' => 'demo.editor@crasadev.invalid', 'is_sample' => true]);
        $this->realAdmin = User::factory()->administrator()->create(['email' => 'secretariat@crasa.org']);
        $this->realEditor = User::factory()->editor()->create(['email' => 'editor@icasa.org.za']);

        $meeting = Meeting::factory()->upcoming()->create(['clearance' => 'public']);

        foreach ([$this->demoEditor, $this->realEditor] as $user) {
            DelegateRegistration::create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'delegate_name' => $user->name,
                'delegate_email' => $user->email,
                'status' => 'filed',
            ]);
            Enquiry::create([
                'name' => $user->name, 'email' => $user->email, 'subject' => 'Spectrum fees',
                'message' => 'A question about the 2025 fee schedule.', 'locale' => 'en',
            ]);
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(), 'type' => 'App\\Notifications\\AdminActivity',
                'notifiable_type' => $user->getMorphClass(), 'notifiable_id' => $user->id,
                'data' => '{}', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('sessions')->insert([
                'id' => Str::random(40), 'user_id' => $user->id, 'payload' => '', 'last_activity' => time(),
            ]);
            DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => 'x', 'created_at' => now()]);
        }

        // A registration filed by a visitor with a demonstration address.
        DelegateRegistration::create([
            'meeting_id' => $meeting->id, 'delegate_name' => 'Demo', 'delegate_email' => 'demo.member@crasadev.invalid', 'status' => 'filed',
        ]);

        // The demonstration editor sent a page that is still in review; the
        // real editor's page was approved by the real administrator.
        $this->page = Page::factory()->create(['status' => PageStatus::Review]);
        Approval::create([
            'approvable_type' => $this->page->getMorphClass(), 'approvable_id' => $this->page->id,
            'state' => Approval::IN_REVIEW, 'submitted_by' => $this->demoEditor->id, 'submitted_at' => now(),
        ]);
        $realPage = Page::factory()->create(['status' => PageStatus::Published]);
        Approval::create([
            'approvable_type' => $realPage->getMorphClass(), 'approvable_id' => $realPage->id,
            'state' => Approval::APPROVED, 'submitted_by' => $this->realEditor->id, 'submitted_at' => now(),
            'decided_by' => $this->realAdmin->id, 'decided_at' => now(),
        ]);
    }

    private function snapshot(): array
    {
        return [
            'users' => User::query()->withoutGlobalScopes()->orderBy('id')->get(['id', 'name', 'email', 'status', 'anonymised_at'])->toArray(),
            'registrations' => DelegateRegistration::withoutGlobalScopes()->count(),
            'enquiries' => Enquiry::count(),
            'notifications' => DB::table('notifications')->count(),
            'sessions' => DB::table('sessions')->count(),
            'tokens' => DB::table('password_reset_tokens')->count(),
            'approvals' => Approval::count(),
            'audit' => AuditLog::count(),
        ];
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        $before = $this->snapshot();

        $this->artisan('crasa:prepare-production', ['--dry-run' => true])
            ->expectsOutputToContain('Dry run')
            ->expectsOutputToContain('content:strip-samples')
            ->assertSuccessful();

        $this->assertSame($before, $this->snapshot());
    }

    public function test_it_removes_exactly_the_demonstration_accounts_and_their_rows(): void
    {
        $auditBefore = AuditLog::count();

        $this->artisan('crasa:prepare-production')->assertSuccessful();

        foreach ([$this->demoAdmin, $this->demoAdmin2, $this->demoEditor] as $demo) {
            $demo->refresh();
            $this->assertNotNull($demo->anonymised_at);
            $this->assertSame('Former user', $demo->name);
            $this->assertSame('former-user-'.$demo->id.'@deleted.invalid', $demo->email);
            $this->assertNull($demo->remember_token);
            $this->assertSame(UserStatus::Suspended, $demo->status);
            $this->assertFalse($demo->is_sample);
        }

        foreach ([$this->realAdmin, $this->realEditor] as $real) {
            $fresh = $real->fresh();
            $this->assertNull($fresh->anonymised_at);
            $this->assertSame($real->email, $fresh->email);
            $this->assertSame($real->remember_token, $fresh->remember_token);
            $this->assertSame(UserStatus::Active, $fresh->status);
        }

        $this->assertSame(1, DB::table('sessions')->where('user_id', $this->realEditor->id)->count());
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $this->realEditor->id)->count());
        $this->assertSame(1, DB::table('password_reset_tokens')->where('email', $this->realEditor->email)->count());

        $this->assertSame(0, DB::table('sessions')->where('user_id', $this->demoEditor->id)->count());
        $this->assertSame(0, DB::table('notifications')->where('notifiable_id', $this->demoEditor->id)->count());
        $this->assertSame(1, DB::table('password_reset_tokens')->count());

        $this->assertSame(['editor@icasa.org.za'], DelegateRegistration::withoutGlobalScopes()->pluck('delegate_email')->all());
        $this->assertSame(['editor@icasa.org.za'], Enquiry::pluck('email')->all());

        // The demonstration approval is gone and its page is editable again.
        $this->assertSame([$this->realEditor->id], Approval::pluck('submitted_by')->all());
        $this->assertSame(PageStatus::Draft, $this->page->fresh()->status);

        // One audit entry, by the console, and the earlier entries kept.
        $this->assertSame(1, AuditLog::where('action', 'production_prepared')->count());
        $entry = AuditLog::where('action', 'production_prepared')->first();
        $this->assertNull($entry->user_id);
        $this->assertSame(3, $entry->changes['counts']['accounts']);
        $this->assertGreaterThan($auditBefore, AuditLog::count());

        // Run again: nothing left to do.
        $this->artisan('crasa:prepare-production')->expectsOutputToContain('Nothing to remove')->assertSuccessful();
    }

    public function test_it_refuses_when_no_administrator_would_remain(): void
    {
        $this->realAdmin->update(['status' => UserStatus::Suspended]);
        $before = $this->snapshot();

        $this->artisan('crasa:prepare-production')
            ->expectsOutputToContain('No administrator would remain')
            ->assertFailed();

        $this->assertSame($before, $this->snapshot());
    }

    public function test_an_invited_administrator_is_enough_to_run(): void
    {
        $this->realAdmin->update(['status' => UserStatus::Invited]);

        $this->artisan('crasa:prepare-production')
            ->expectsOutputToContain('must set a password')
            ->assertSuccessful();

        $this->assertNotNull($this->demoAdmin->fresh()->anonymised_at);
    }

    public function test_it_refuses_another_host_unless_forced(): void
    {
        config(['app.url' => 'https://crasadev.paneo.tech']);
        $before = $this->snapshot();

        $this->artisan('crasa:prepare-production')->expectsOutputToContain('not crasa.africa')->assertFailed();
        $this->artisan('crasa:prepare-production', ['--dry-run' => true])->assertFailed();
        $this->assertSame($before, $this->snapshot());

        $this->artisan('crasa:prepare-production', ['--force-host' => true])->assertSuccessful();
        $this->assertNotNull($this->demoAdmin->fresh()->anonymised_at);
    }
}

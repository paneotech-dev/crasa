<?php

namespace Tests\Feature;

use App\Enums\NotificationTrigger;
use App\Models\Country;
use App\Models\User;
use App\Notifications\RegulatoryActivity;
use App\Support\Notifier;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Client brief item 7, signed in as each of the seven roles: the bell
 * shows the unread count and opens the list, each entry links to its item,
 * one or all can be marked read, the notifications page filters by type and
 * by read state, and the help icon opens the help for the current screen.
 */
class NotifyPerRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['crasa.two_factor_required' => false]);
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(CommitteeSeeder::class);
    }

    /** @return array<string, array{string, string}> role => [factory state, the screen they work on] */
    public static function roles(): array
    {
        return [
            'administrator' => ['administrator', 'admin.dashboard'],
            'moderator' => ['moderator', 'admin.dashboard'],
            'editor' => ['editor', 'admin.dashboard'],
            'auditor' => ['auditor', 'admin.dashboard'],
            'member state representative' => ['memberStateRep', 'extranet.dashboard'],
            'committee member' => ['committeeMember', 'extranet.dashboard'],
            'viewer' => ['viewer', 'notifications'],
        ];
    }

    private function make(string $state): User
    {
        $botswana = Country::where('iso_code', 'bw')->sole();

        return in_array($state, ['memberStateRep', 'committeeMember'], true)
            ? User::factory()->{$state}($botswana)->create()
            : User::factory()->{$state}()->create();
    }

    private function tell(User $user, string $title, NotificationTrigger $trigger = NotificationTrigger::Announcement): void
    {
        Notifier::send([$user], new RegulatoryActivity($trigger, $title, url('/en/notifications')), Notifier::categoryOfTrigger($trigger));
    }

    #[DataProvider('roles')]
    public function test_the_bell_shows_the_count_and_lists_the_notices(string $state, string $screen): void
    {
        $user = $this->make($state);
        $this->tell($user, 'First notice for this role');
        $this->tell($user, 'A meeting moved for this role', NotificationTrigger::MeetingChanged);

        $html = $this->actingAs($user)->get(route($screen, ['locale' => 'en']))->assertOk()->getContent();

        $this->assertStringContainsString('data-bell', $html);
        $this->assertStringContainsString('Notifications, 2 unread', $html);
        $this->assertStringContainsString('<span class="a-top__badge" aria-hidden="true">2</span>', $html);
        $this->assertStringContainsString('First notice for this role', $html);
        $this->assertStringContainsString('Meeting changes', $html);
        $this->assertStringContainsString(e(__('d3_notify.mark_read')), $html);
        $this->assertStringContainsString('href="'.route('notifications', ['locale' => 'en']).'"', $html);

        // Each entry links to its item through the open route.
        $id = $user->notifications()->latest()->first()->id;
        $this->assertStringContainsString(route('notifications.open', ['locale' => 'en', 'notification' => $id]), $html);
    }

    #[DataProvider('roles')]
    public function test_one_and_then_all_are_marked_read(string $state, string $screen): void
    {
        $user = $this->make($state);
        $this->tell($user, 'One');
        $this->tell($user, 'Two');
        $this->tell($user, 'Three');

        $first = $user->notifications()->first();

        $this->actingAs($user)->post(route('notifications.read', ['locale' => 'en', 'notification' => $first->id]))
            ->assertRedirect()->assertSessionHas('status', __('d3_notify.marked_read'));
        $this->assertNotNull($first->fresh()->read_at);
        $this->assertSame(2, $user->unreadNotifications()->count());

        $this->actingAs($user)->post(route('notifications.read-all', ['locale' => 'en']))->assertRedirect();
        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    #[DataProvider('roles')]
    public function test_opening_a_notice_marks_it_read_and_goes_to_its_item_in_the_current_language(string $state, string $screen): void
    {
        $user = $this->make($state);
        Notifier::send([$user], new RegulatoryActivity(NotificationTrigger::Announcement, 'Go to the account', url('/en/account')), 'announcement');
        $notice = $user->notifications()->sole();

        $this->actingAs($user)->get('/fr/notifications/'.$notice->id)->assertRedirect(url('/fr/account'));
        $this->assertNotNull($notice->fresh()->read_at);
    }

    #[DataProvider('roles')]
    public function test_the_notifications_page_filters_by_type_and_by_read_state(string $state, string $screen): void
    {
        $user = $this->make($state);
        $this->tell($user, 'An announcement notice');
        $this->tell($user, 'A document notice', NotificationTrigger::CommitteeDocument);
        $this->tell($user, 'A meeting notice', NotificationTrigger::MeetingChanged);
        $user->notifications()->get()->firstWhere(fn ($n) => $n->data['title'] === 'A meeting notice')->markAsRead();

        // The page body only: the bell in the top bar lists the latest
        // notices whatever the filter.
        $main = fn (string $query) => strstr($this->actingAs($user)->get('/en/notifications'.$query)->assertOk()->getContent(), '<main');

        $page = $main('?type=document');
        $this->assertStringContainsString('A document notice', $page);
        $this->assertStringNotContainsString('An announcement notice', $page);
        $this->assertStringNotContainsString('A meeting notice', $page);

        $page = $main('?state=unread');
        $this->assertStringContainsString('A document notice', $page);
        $this->assertStringContainsString('An announcement notice', $page);
        $this->assertStringNotContainsString('A meeting notice', $page);

        $page = $main('?state=read&type=meeting');
        $this->assertStringContainsString('A meeting notice', $page);
        $this->assertStringNotContainsString('A document notice', $page);

        $this->assertStringContainsString(e(__('d3_notify.filtered_empty_title')), $main('?type=registration'));

        // An unknown filter value is ignored, never an error.
        $this->assertStringContainsString('A document notice', $main('?type=flights&state=maybe'));
    }

    #[DataProvider('roles')]
    public function test_the_help_icon_opens_the_help_for_the_current_screen(string $state, string $screen): void
    {
        $user = $this->make($state);

        $html = $this->actingAs($user)->get(route($screen, ['locale' => 'en']))->assertOk()->getContent();
        $this->assertStringContainsString('href="'.url('/en/help/'.$screen), $html);

        // The merged entry: a stream's own file may supersede the core one
        // (the member home's help is in screenhelp_look since its redesign).
        $help = \App\Support\ScreenHelp::for($screen, 'en');

        $this->actingAs($user)->get('/en/help/'.$screen)
            ->assertOk()
            ->assertSee($help['purpose'])
            ->assertSee($help['actions'][0])
            ->assertSee($help['actions'][2])
            ->assertSee(config('crasa.role_guide_url'), false)
            ->assertSee(__('d3_notify.help_guide'));

        // And in French.
        $this->actingAs($user)->get('/fr/help/'.$screen)->assertOk()->assertSee(\App\Support\ScreenHelp::for($screen, 'fr')['purpose']);
    }

    #[DataProvider('roles')]
    public function test_nobody_reads_or_marks_another_persons_notices(string $state, string $screen): void
    {
        $user = $this->make($state);
        $other = User::factory()->editor()->create();
        $this->tell($other, 'Not yours');
        $theirs = $other->notifications()->sole();

        $this->actingAs($user)->get('/en/notifications/'.$theirs->id)->assertNotFound();
        $this->actingAs($user)->post('/en/notifications/'.$theirs->id.'/read')->assertNotFound();
        $this->assertNull($theirs->fresh()->read_at);
        $this->actingAs($user)->get('/en/notifications')->assertOk()->assertDontSee('Not yours');
    }

    public function test_back_office_help_is_refused_to_roles_without_the_back_office(): void
    {
        foreach (['memberStateRep', 'committeeMember', 'viewer'] as $state) {
            $this->actingAs($this->make($state))->get('/en/help/admin.users')->assertForbidden();
        }

        $this->actingAs($this->make('editor'))->get('/en/help/admin.users')->assertOk();
    }

    public function test_help_for_an_unknown_screen_is_not_found_and_guests_sign_in(): void
    {
        $this->actingAs($this->make('editor'))->get('/en/help/no.such.screen')->assertNotFound();
        $this->actingAs($this->make('editor'))->get('/en/help/notifications.read')->assertNotFound();

        auth()->logout();
        $this->get('/en/help/notifications')->assertRedirect();
        $this->post('/en/notifications/abc/read')->assertRedirect();
    }

    public function test_the_latest_partial_takes_a_limit(): void
    {
        $user = $this->make('memberStateRep');
        foreach (range(1, 6) as $n) {
            $this->tell($user, 'Notice number '.$n);
        }

        $this->actingAs($user);
        $html = view('partials.notifications.latest', ['limit' => 3])->render();

        $this->assertSame(3, substr_count($html, 'class="n-latest__link"'));
    }
}

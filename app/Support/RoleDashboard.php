<?php

namespace App\Support;

use App\Enums\Permission;
use App\Enums\SubmissionStatus;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Committee;
use App\Models\Document;
use App\Models\Meeting;
use App\Models\NewsItem;
use App\Models\Page;
use App\Models\Publication;
use App\Models\SocialPost;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * The blocks at the top of each role's dashboard (F4).
 *
 * One shape for every block: a title, a list of rows (label, url, meta,
 * comment), an empty text that says why the block is empty and what to do,
 * and at most two actions. The view draws them; this class decides which
 * a reader gets, and every row is something the reader may open.
 *
 * The approvals inbox, returned items and their counts come from
 * App\Support\Approvals (the moderation work). It is called only when the
 * class is there, so this works before and after that work is merged.
 */
final class RoleDashboard
{
    /**
     * @return array{role: string, heading: string, intro: string, blocks: list<array<string, mixed>>, figures: list<array<string, mixed>>, shortcuts: list<array{label: string, url: string}>}
     */
    public static function for(User $user): array
    {
        $role = self::roleKey($user);

        $blocks = match ($role) {
            'administrator' => [self::attention($user), self::adminNotices($user), self::returned($user)],
            'moderator' => [self::inbox($user), self::recentlyApproved()],
            'editor' => [self::myDrafts($user), self::returned($user), self::live()],
            'auditor' => [self::auditTrail($user), self::live()],
            'member_state_rep' => [self::countryReturn($user), self::countryIndicators($user), self::upcoming($user), self::documents($user)],
            'committee_member' => [self::myCommittees($user), self::nextMeeting($user), self::documents($user)],
            default => [self::live(), self::comparator()],
        };

        return [
            'role' => $role,
            'heading' => __('fb.dash_heading_'.$role),
            'intro' => __('fb.dash_intro_'.$role),
            'blocks' => array_values(array_filter($blocks)),
            'figures' => $role === 'administrator' ? self::figures() : [],
            'shortcuts' => self::shortcuts($user, $role),
        ];
    }

    /** Country returns waiting for validation, for the sidebar badge. */
    public static function submissionsWaiting(): int
    {
        return Submission::where('status', SubmissionStatus::Submitted)->count();
    }

    /** Social posts saved but not on the site, for the sidebar badge. */
    public static function socialDrafts(): int
    {
        return Schema::hasTable('social_posts') ? SocialPost::where('status', 'draft')->count() : 0;
    }

    /** The role this dashboard is drawn for, as a plain key. */
    public static function roleKey(User $user): string
    {
        $value = $user->role instanceof \BackedEnum ? $user->role->value : (string) $user->role;

        return in_array($value, ['administrator', 'moderator', 'editor', 'auditor', 'member_state_rep', 'committee_member', 'viewer'], true)
            ? $value
            : 'viewer';
    }

    /* ------------------------------------------------------------------ */
    /* Approvals, through the moderation work when it is there             */
    /* ------------------------------------------------------------------ */

    public static function approvalsReady(): bool
    {
        return class_exists(\App\Support\Approvals::class)
            && method_exists(\App\Support\Approvals::class, 'awaitingCountFor');
    }

    /** Items waiting for this reader's decision; null when there is no approval flow. */
    public static function awaitingCount(?User $user): ?int
    {
        if (! self::approvalsReady() || $user === null) {
            return null;
        }

        try {
            return (int) \App\Support\Approvals::awaitingCountFor($user);
        } catch (Throwable) {
            return null;
        }
    }

    private static function approvalRow(object $approval, bool $withComment = false): array
    {
        $model = $approval->approvable ?? null;
        $title = method_exists(\App\Support\Approvals::class, 'title')
            ? \App\Support\Approvals::title($model)
            : class_basename((string) ($approval->approvable_type ?? ''));
        $url = Route::has('admin.approvals.show')
            ? route('admin.approvals.show', ['approval' => $approval->id])
            : null;
        $when = $approval->decided_at ?? $approval->submitted_at ?? null;

        return [
            'label' => $title,
            'url' => $url,
            'meta' => trim(implode(', ', array_filter([
                $approval->submitter->name ?? null,
                $when ? $when->isoFormat('D MMMM YYYY') : null,
            ]))),
            'comment' => $withComment ? ($approval->comment ?? null) : null,
        ];
    }

    private static function inbox(User $user): array
    {
        $rows = [];

        if (self::approvalsReady()) {
            try {
                $rows = \App\Support\Approvals::awaitingFor($user, 8)->map(fn ($a) => self::approvalRow($a))->all();
            } catch (Throwable) {
                $rows = [];
            }
        }

        return [
            'key' => 'inbox',
            'title' => __('fb.dash_inbox'),
            'count' => self::awaitingCount($user),
            'rows' => $rows,
            'empty' => self::approvalsReady() ? __('fb.dash_inbox_empty') : __('fb.dash_no_approvals'),
            'actions' => Route::has('admin.approvals') ? [['label' => __('fb.dash_open_inbox'), 'url' => route('admin.approvals')]] : [],
            'wide' => true,
        ];
    }

    private static function recentlyApproved(): ?array
    {
        if (! self::approvalsReady() || ! class_exists(\App\Models\Approval::class)) {
            return null;
        }

        try {
            $rows = \App\Models\Approval::where('state', 'approved')
                ->with(['approvable', 'submitter', 'decider'])
                ->latest('decided_at')->limit(6)->get()
                ->map(fn ($a) => self::approvalRow($a))->all();
        } catch (Throwable) {
            $rows = [];
        }

        return [
            'key' => 'approved',
            'title' => __('fb.dash_recently_approved'),
            'rows' => $rows,
            'empty' => __('fb.dash_recently_approved_empty'),
            'actions' => [],
        ];
    }

    private static function returned(User $user): ?array
    {
        if (! self::approvalsReady() || ! method_exists(\App\Support\Approvals::class, 'returnedTo')) {
            return null;
        }

        try {
            $rows = \App\Support\Approvals::returnedTo($user, 6)->map(fn ($a) => self::approvalRow($a, true))->values()->all();
        } catch (Throwable) {
            $rows = [];
        }

        return [
            'key' => 'returned',
            'title' => __('fb.dash_returned'),
            'count' => count($rows),
            'rows' => $rows,
            'empty' => __('fb.dash_returned_empty'),
            'actions' => [],
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Administrator                                                        */
    /* ------------------------------------------------------------------ */

    private static function attention(User $user): array
    {
        $rows = [];

        $awaiting = self::awaitingCount($user);
        if ($awaiting !== null && $awaiting > 0) {
            $rows[] = ['label' => trans_choice('fb.dash_att_approvals', $awaiting, ['count' => $awaiting]),
                'url' => Route::has('admin.approvals') ? route('admin.approvals') : null, 'meta' => null, 'comment' => null];
        }

        $submissions = self::submissionsWaiting();
        if ($submissions > 0) {
            $rows[] = ['label' => trans_choice('fb.dash_att_submissions', $submissions, ['count' => $submissions]),
                'url' => Route::has('admin.submissions') ? route('admin.submissions') : null, 'meta' => null, 'comment' => null];
        }

        // Invitations run for seven days (the invitations broker). One sent
        // more than four days ago is about to lapse or has.
        $expiring = User::where('status', UserStatus::Invited)->where('created_at', '<', now()->subDays(4))->count();
        if ($expiring > 0) {
            $rows[] = ['label' => trans_choice('fb.dash_att_invitations', $expiring, ['count' => $expiring]),
                'url' => Route::has('admin.users') ? route('admin.users', ['status' => 'invited']) : null, 'meta' => __('fb.dash_att_invitations_meta'), 'comment' => null];
        }

        $drafts = self::socialDrafts();
        if ($drafts > 0) {
            $rows[] = ['label' => trans_choice('fb.dash_att_social', $drafts, ['count' => $drafts]),
                'url' => route('admin.social'), 'meta' => null, 'comment' => null];
        }

        $mailer = MailStatus::mailer();
        $mailOff = MailStatus::isOff();

        return [
            'key' => 'attention',
            'title' => __('fb.dash_attention'),
            'count' => count($rows),
            'rows' => $rows,
            'status' => [
                'label' => $mailOff ? __('fb.dash_mail_off', ['mailer' => $mailer ?: 'none']) : __('fb.dash_mail_on', ['mailer' => $mailer]),
                'tone' => $mailOff ? 'notice' : 'ok',
            ],
            'empty' => __('fb.dash_attention_empty'),
            'actions' => [],
            'wide' => true,
        ];
    }

    /** Other administrators' actions (suspensions, role changes, publishing without approval). */
    private static function adminNotices(User $user): ?array
    {
        if (! class_exists(\App\Support\AdminAlerts::class) || ! method_exists(\App\Support\AdminAlerts::class, 'recentFor')) {
            return null;
        }

        try {
            $rows = \App\Support\AdminAlerts::recentFor($user, 5)->map(fn ($n) => [
                'label' => $n['title'],
                'url' => $n['url'],
                'meta' => $n['at']?->displayed()->isoFormat('D MMMM YYYY HH:mm'),
                'comment' => null,
            ])->values()->all();
        } catch (Throwable) {
            $rows = [];
        }

        return [
            'key' => 'notices',
            'title' => __('fb.dash_notices'),
            'rows' => $rows,
            'empty' => __('fb.dash_notices_empty'),
            'actions' => Route::has('notifications') ? [['label' => __('fb.dash_open_notifications'), 'url' => route('notifications')]] : [],
        ];
    }

    /**
     * Three counts with eight weeks of history each. Every figure is counted
     * from the database now; a week with nothing is a zero, not a gap.
     *
     * @return list<array{label: string, n: int, series: list<int>, url: ?string}>
     */
    public static function figures(): array
    {
        $weeks = collect(range(7, 0))->map(fn ($i) => now()->startOfWeek()->subWeeks($i));
        $since = $weeks->first();

        $series = function (Collection $dates) use ($weeks): array {
            return $weeks->map(fn (Carbon $start) => $dates->filter(
                fn ($d) => $d !== null && Carbon::parse($d)->betweenIncluded($start, $start->copy()->endOfWeek())
            )->count())->all();
        };

        $news = NewsItem::where('published_at', '>=', $since)->pluck('published_at');
        $audit = AuditLog::where('created_at', '>=', $since)->pluck('created_at');
        $logins = AuditLog::where('action', AuditLog::LOGIN)->where('created_at', '>=', $since)->pluck('created_at');

        return [
            ['label' => __('fb.dash_fig_news'), 'n' => PublishedCounts::news(), 'series' => $series($news),
                'url' => route('admin.resources', ['resource' => 'news'])],
            ['label' => __('fb.dash_fig_changes'), 'n' => $audit->count(), 'series' => $series($audit),
                'url' => Route::has('admin.audit') ? route('admin.audit') : null],
            ['label' => __('fb.dash_fig_signins'), 'n' => $logins->count(), 'series' => $series($logins),
                'url' => Route::has('admin.audit') ? route('admin.audit') : null],
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Editor                                                               */
    /* ------------------------------------------------------------------ */

    private static function myDrafts(User $user): array
    {
        $rows = collect();

        $rows = $rows->merge(NewsItem::where('status', 'draft')->with('translations')->latest('updated_at')->limit(4)->get()
            ->map(fn (NewsItem $n) => [
                'label' => $n->translated('title', $n->slug),
                'url' => route('admin.resources.edit', ['resource' => 'news', 'id' => $n->id]),
                'meta' => __('fb.dash_type_news').', '.$n->updated_at?->isoFormat('D MMMM YYYY'),
                'comment' => null,
            ]));

        $rows = $rows->merge(Page::where('status', \App\Enums\PageStatus::Draft)->with('translations')->latest('updated_at')->limit(3)->get()
            ->map(fn (Page $p) => [
                'label' => $p->translated('title', $p->slug),
                'url' => route('admin.pages.edit', ['page' => $p->slug]),
                'meta' => __('fb.dash_type_page').', '.$p->updated_at?->isoFormat('D MMMM YYYY'),
                'comment' => null,
            ]));

        return [
            'key' => 'drafts',
            'title' => __('fb.dash_drafts'),
            'count' => $rows->count(),
            'rows' => $rows->values()->all(),
            'empty' => __('fb.dash_drafts_empty'),
            'actions' => [],
        ];
    }

    private static function live(): array
    {
        $news = NewsItem::published()->with('translations')->latest('published_at')->limit(4)->get()
            ->map(fn (NewsItem $n) => [
                'label' => $n->translated('title', $n->slug),
                'url' => route('newsroom.show', ['slug' => $n->slug]),
                'meta' => __('fb.dash_type_news').', '.$n->published_at?->isoFormat('D MMMM YYYY'),
                'comment' => null,
            ]);

        $publications = PublishedCounts::publicationsQuery()->with('translations')->orderByDesc('published_on')->limit(3)->get()
            ->map(fn (Publication $p) => [
                'label' => $p->translated('title', $p->slug),
                'url' => route('publications.show', ['slug' => $p->slug]),
                'meta' => __('fb.dash_type_publication').($p->published_on ? ', '.$p->published_on->isoFormat('D MMMM YYYY') : ''),
                'comment' => null,
            ]);

        return [
            'key' => 'live',
            'title' => __('fb.dash_live'),
            'rows' => $news->merge($publications)->values()->all(),
            'empty' => __('fb.dash_live_empty'),
            'actions' => [['label' => __('fb.dash_open_site'), 'url' => route('home')]],
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Auditor                                                              */
    /* ------------------------------------------------------------------ */

    private static function auditTrail(User $user): array
    {
        $may = Gate::forUser($user)->allows(Permission::ReadAuditLog->value);
        $rows = $may
            ? AuditLog::with('user')->latest('created_at')->limit(8)->get()->map(fn (AuditLog $a) => [
                'label' => Str::headline((string) $a->action).($a->auditable_type ? ': '.class_basename($a->auditable_type) : ''),
                'url' => null,
                'meta' => trim(($a->user->name ?? __('fb.dash_system')).', '.$a->created_at?->displayed()->isoFormat('D MMMM YYYY HH:mm')),
                'comment' => null,
            ])->all()
            : [];

        return [
            'key' => 'audit',
            'title' => __('fb.dash_audit'),
            'rows' => $rows,
            'empty' => $may ? __('fb.dash_audit_empty') : __('fb.dash_audit_denied'),
            'actions' => $may && Route::has('admin.audit') ? [['label' => __('fb.dash_open_audit'), 'url' => route('admin.audit')]] : [],
            'wide' => true,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Member State Representative                                          */
    /* ------------------------------------------------------------------ */

    private static function countryReturn(User $user): array
    {
        $country = $user->country;
        $latest = $country ? Submission::where('country_id', $country->id)->latest('updated_at')->first() : null;
        $status = $latest?->status instanceof \BackedEnum ? $latest->status->value : (string) ($latest->status ?? '');

        $next = match ($status) {
            'draft' => __('fb.dash_next_draft'),
            'submitted' => __('fb.dash_next_submitted'),
            'returned' => __('fb.dash_next_returned'),
            'validated' => __('fb.dash_next_validated'),
            default => __('fb.dash_next_none'),
        };

        $rows = $latest ? [[
            'label' => __('fb.dash_return_row', ['period' => $latest->period, 'status' => __('fb.dash_status_'.($status ?: 'none'))]),
            'url' => Route::has('submissions.edit') && in_array($status, ['draft', 'returned'], true)
                ? route('submissions.edit', ['submission' => $latest->id])
                : (Route::has('submissions') ? route('submissions') : null),
            'meta' => $latest->updated_at?->isoFormat('D MMMM YYYY'),
            'comment' => $status === 'returned' ? $latest->review_note : null,
        ]] : [];

        return [
            'key' => 'return',
            'title' => __('fb.dash_return', ['country' => $country->name ?? '']),
            'rows' => $rows,
            'next' => $next,
            'empty' => $country ? __('fb.dash_return_empty') : __('fb.dash_no_country'),
            'actions' => Route::has('submissions') && Gate::forUser($user)->allows('viewAny', Submission::class)
                ? [['label' => __('fb.dash_open_returns'), 'url' => route('submissions')]] : [],
        ];
    }

    private static function countryIndicators(User $user): array
    {
        $country = $user->country;
        $count = $country ? $country->indicatorValues()->count() : 0;
        $indicators = $country ? $country->indicatorValues()->distinct()->count('indicator_id') : 0;

        return [
            'key' => 'indicators',
            'title' => __('fb.dash_indicators', ['country' => $country->name ?? '']),
            'rows' => $count > 0 ? [[
                'label' => trans_choice('fb.dash_indicator_values', $count, ['count' => $count]),
                'url' => Route::has('comparator') ? route('comparator', $country ? ['countries' => strtolower((string) $country->iso_code)] : []) : null,
                'meta' => trans_choice('fb.dash_indicator_count', $indicators, ['count' => $indicators]),
                'comment' => null,
            ]] : [],
            'empty' => __('fb.dash_indicators_empty'),
            'actions' => Route::has('completeness') ? [['label' => __('fb.dash_open_completeness'), 'url' => route('completeness')]] : [],
        ];
    }

    private static function upcoming(User $user): array
    {
        // Only meetings this reader may see, as on the member dashboard below:
        // another committee's or a restricted meeting is never listed.
        // A member's dashboard lists their own committees' meetings and the
        // CRASA-wide ones; the Secretariat (no seats) sees every meeting.
        $ids = $user->committees()->pluck('committees.id');
        $rows = Meeting::upcoming()->with('committee')
            ->when($ids->isNotEmpty() || ! $user->hasPermission(Permission::ManageMeetings),
                fn ($q) => $q->where(fn ($q) => $q->whereIn('committee_id', $ids)->orWhereNull('committee_id')))
            ->limit(12)->get()
            ->filter(fn (Meeting $m) => Gate::forUser($user)->allows('view', $m))
            ->take(3)->map(fn (Meeting $m) => self::meetingRow($m))->values()->all();

        return [
            'key' => 'upcoming',
            'title' => __('fb.dash_upcoming'),
            'rows' => $rows,
            'empty' => __('fb.dash_upcoming_empty'),
            'actions' => Route::has('extranet.calendar') ? [['label' => __('fb.dash_open_calendar'), 'url' => route('extranet.calendar')]] : [],
        ];
    }

    private static function meetingRow(Meeting $m): array
    {
        // The public page and its .ics answer only for a meeting the public
        // may see; any other meeting opens in the member calendar.
        $public = Gate::forUser(null)->allows('view', $m);

        return [
            'label' => $m->title,
            'url' => $public ? route('events.show', ['meeting' => $m]) : (Route::has('extranet.calendar') ? route('extranet.calendar') : null),
            'meta' => trim(implode(', ', array_filter([$m->starts_on?->isoFormat('D MMMM YYYY'), $m->location]))),
            'comment' => null,
            'ics' => $public ? route('events.ics', ['meeting' => $m]) : null,
        ];
    }

    private static function documents(User $user): array
    {
        $rows = [];

        try {
            $rows = Document::query()->latest('created_at')->limit(12)->get()
                ->filter(fn (Document $d) => Gate::forUser($user)->allows('view', $d))
                ->take(4)
                ->map(fn (Document $d) => [
                    'label' => $d->original_filename,
                    'url' => route('documents.show', ['document' => $d->id]),
                    'meta' => $d->created_at?->isoFormat('D MMMM YYYY'),
                    'comment' => null,
                ])->values()->all();
        } catch (Throwable) {
            $rows = [];
        }

        return [
            'key' => 'documents',
            'title' => __('fb.dash_documents'),
            'rows' => $rows,
            'empty' => __('fb.dash_documents_empty'),
            'actions' => Route::has('extranet.folders') ? [['label' => __('fb.dash_open_folders'), 'url' => route('extranet.folders')]] : [],
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Committee Member                                                     */
    /* ------------------------------------------------------------------ */

    private static function myCommittees(User $user): array
    {
        $rows = $user->committees()->orderBy('name')->get()->map(fn (Committee $c) => [
            'label' => $c->name,
            'url' => Route::has('extranet.committee') ? route('extranet.committee', ['committee' => $c->slug]) : null,
            'meta' => null,
            'comment' => null,
        ])->all();

        return [
            'key' => 'committees',
            'title' => __('fb.dash_committees'),
            'rows' => $rows,
            'empty' => __('fb.dash_committees_empty'),
            'actions' => [],
        ];
    }

    private static function nextMeeting(User $user): array
    {
        $ids = $user->committees()->pluck('committees.id');
        $meeting = Meeting::upcoming()
            ->when($ids->isNotEmpty(), fn ($q) => $q->where(fn ($q) => $q->whereIn('committee_id', $ids)->orWhereNull('committee_id')))
            ->limit(12)->get()
            ->first(fn (Meeting $m) => Gate::forUser($user)->allows('view', $m));

        return [
            'key' => 'next',
            'title' => __('fb.dash_next_meeting'),
            'rows' => $meeting ? [self::meetingRow($meeting)] : [],
            'empty' => __('fb.dash_next_meeting_empty'),
            'actions' => Route::has('extranet.calendar') ? [['label' => __('fb.dash_open_calendar'), 'url' => route('extranet.calendar')]] : [],
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Viewer                                                               */
    /* ------------------------------------------------------------------ */

    private static function comparator(): array
    {
        return [
            'key' => 'comparator',
            'title' => __('fb.dash_comparator'),
            'rows' => [],
            'empty' => __('fb.dash_comparator_body'),
            'actions' => [['label' => __('fb.dash_open_comparator'), 'url' => route('comparator')]],
            'plain' => true,
        ];
    }

    /** @return list<array{label: string, url: string}> */
    private static function shortcuts(User $user, string $role): array
    {
        $can = fn (Permission $p) => Gate::forUser($user)->allows($p->value);
        $out = [];

        if ($can(Permission::ManageContent)) {
            $out[] = ['label' => __('fb.dash_new_news'), 'url' => route('admin.resources.create', ['resource' => 'news'])];
            $out[] = ['label' => __('fb.dash_new_publication'), 'url' => route('admin.resources.create', ['resource' => 'publications'])];
            $out[] = ['label' => __('fb.social_add'), 'url' => route('admin.social.create')];
        }
        if ($can(Permission::ManageUsers) && Route::has('admin.users')) {
            $out[] = ['label' => __('fb.dash_users'), 'url' => route('admin.users')];
        }
        if (in_array($role, ['moderator', 'administrator'], true) && Route::has('admin.approvals')) {
            $out[] = ['label' => __('fb.dash_open_inbox'), 'url' => route('admin.approvals')];
        }

        return $out;
    }
}

<?php

namespace App\Support;

use App\Enums\SubmissionStatus;
use App\Models\Announcement;
use App\Models\Document;
use App\Models\Folder;
use App\Models\Indicator;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

/**
 * The member area's home page and notice list, Day 3 (8c).
 *
 * Everything here is read through the same scope or Gate as the page it
 * links to: submissions carry the country scope, folders and documents go
 * through their policies, announcements through visibleTo().
 */
final class MemberHome
{
    /** A notice published in the last fourteen days and not yet opened is new. */
    public const NEW_DAYS = 14;

    /** @return array{name: string, line: string} */
    public static function greeting(User $user): array
    {
        $line = $user->authority?->name
            ?? ($user->country?->name)
            ?? $user->roleLabel();

        return ['name' => $user->name, 'line' => $line];
    }

    /**
     * The return the member's country should be working on now, its
     * completion and the next field to fill.
     *
     * Order: a return the Secretariat sent back, then a draft, then the
     * oldest open period with nothing filed (the one that is most overdue).
     *
     * @return array{period: string, status: string, submission: ?Submission, filled: int, total: int, percent: int, next: ?Indicator, due_on: Carbon, provisional: bool, editable: bool, can_open: bool}|null
     */
    public static function myReturn(User $user): ?array
    {
        if ($user->country_id === null || ! Gate::forUser($user)->allows('viewAny', Submission::class)) {
            return null;
        }

        $open = collect(ReportingPeriods::open());
        $submissions = Submission::query()
            ->where('country_id', $user->country_id)
            ->whereIn('period', $open->pluck('period'))
            ->with('values')
            ->get()
            ->keyBy('period');

        $pick = $submissions->first(fn (Submission $s) => $s->status === SubmissionStatus::Returned)
            ?? $submissions->first(fn (Submission $s) => $s->status === SubmissionStatus::Draft);

        $unstarted = $open->reverse()->first(fn (array $p) => ! $submissions->has($p['period']));
        $period = $pick?->period ?? $unstarted['period'] ?? $open->first()['period'];

        $submission = $pick ?? $submissions->get($period);
        $indicators = Indicator::active()->orderBy('display_order')->get();
        $values = $submission?->values->keyBy('indicator_id') ?? collect();

        $complete = $indicators->filter(fn (Indicator $i) => $values->get($i->id)?->isComplete() ?? false);
        $next = $indicators->first(fn (Indicator $i) => ! ($values->get($i->id)?->isComplete() ?? false));
        $total = $indicators->count();
        $described = ReportingPeriods::describe($period);

        return [
            'period' => $period,
            'status' => $submission?->status->value ?? 'not_started',
            'submission' => $submission,
            'filled' => $complete->count(),
            'total' => $total,
            'percent' => $total > 0 ? (int) round($complete->count() / $total * 100) : 0,
            'next' => $submission === null || $submission->isEditable() ? $next : null,
            'due_on' => $described['due_on'],
            'provisional' => $described['provisional'],
            'editable' => $submission !== null && Gate::forUser($user)->allows('update', $submission),
            'can_open' => $submission === null && Gate::forUser($user)->allows('create', Submission::class),
        ];
    }

    /**
     * The reader's folders and the last three files filed in any of them.
     *
     * @return array{folders: Collection, files: Collection}
     */
    public static function folders(User $user): array
    {
        if (! Gate::forUser($user)->allows('viewAny', Folder::class)) {
            return ['folders' => collect(), 'files' => collect()];
        }

        $folders = Folder::roots()->withCount('documents')->orderBy('name')->get()
            ->filter(fn (Folder $f) => Gate::forUser($user)->allows('view', $f))
            ->values();

        $files = Document::query()->whereNotNull('folder_id')->with('folder')
            ->latest()->limit(30)->get()
            ->filter(fn (Document $d) => Gate::forUser($user)->allows('view', $d))
            ->take(3)
            ->values();

        return ['folders' => $folders->take(4), 'files' => $files];
    }

    /** @return Collection<int, int> ids of the notices this reader has opened */
    public static function readIds(User $user): Collection
    {
        if (! Schema::hasTable('announcement_reads')) {
            return collect();
        }

        return DB::table('announcement_reads')->where('user_id', $user->id)->pluck('announcement_id');
    }

    public static function markRead(User $user, Announcement $announcement): void
    {
        if (! Schema::hasTable('announcement_reads')) {
            return;
        }

        DB::table('announcement_reads')->insertOrIgnore([
            'announcement_id' => $announcement->id,
            'user_id' => $user->id,
            'read_at' => now(),
        ]);
    }

    /**
     * One notice as the list draws it.
     *
     * The type is the audience the Secretariat chose: every member state,
     * one sector, or one committee. It is what a reader filters by.
     *
     * @return array{model: Announcement, type: string, type_label: string, summary: string, read: bool, new: bool, attachments: Collection}
     */
    public static function notice(Announcement $announcement, Collection $readIds, ?User $user): array
    {
        $type = $announcement->committee_id ? 'committee' : ($announcement->sector_id ? 'sector' : 'general');
        $read = $readIds->contains($announcement->id);
        $body = (string) ($announcement->translate('body')?->value ?? '');

        return [
            'model' => $announcement,
            'type' => $type,
            'type_label' => match ($type) {
                'committee' => $announcement->committee?->name ?? __('d3_look.type_committee'),
                'sector' => $announcement->sector?->name ?? __('d3_look.type_sector'),
                default => __('d3_look.type_general'),
            },
            'summary' => \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags(\Illuminate\Support\Str::markdown($body)))), 220),
            'read' => $read,
            'new' => ! $read && $announcement->published_at?->gt(now()->subDays(self::NEW_DAYS)),
            'attachments' => $announcement->relationLoaded('documents')
                ? $announcement->documents->filter(fn (Document $d) => Gate::forUser($user)->allows('view', $d))->values()
                : collect(),
        ];
    }

    /** @return Collection<int, array> the latest notices for the home page */
    public static function latestNotices(User $user, int $limit = 3): Collection
    {
        $read = self::readIds($user);

        return Announcement::live()->visibleTo($user)
            ->with(['translations', 'sector', 'committee', 'documents'])
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get()
            ->map(fn (Announcement $a) => self::notice($a, $read, $user));
    }

    /** A notification's line, for the fallback list. */
    public static function notificationLabel(object $notification): string
    {
        $data = (array) $notification->data;

        if (filled($data['title'] ?? null)) {
            return (string) $data['title'];
        }

        if (($data['type'] ?? null) === 'submission' && isset($data['period'], $data['status'])) {
            return __('d3_look.notification_return', [
                'period' => $data['period'],
                'status' => __('submissions.status_'.$data['status']),
            ]);
        }

        return __('notifications.untitled');
    }

    /**
     * Quick actions: plain links to the pages a member opens most.
     *
     * @return list<array{label: string, url: string}>
     */
    public static function quickActions(User $user): array
    {
        $locale = app()->getLocale();
        $gate = Gate::forUser($user);
        $out = [];

        if ($gate->allows('viewAny', Submission::class)) {
            $out[] = ['label' => __('d3_look.qa_returns'), 'url' => route('submissions', ['locale' => $locale])];
        }
        if ($gate->allows('viewAny', \App\Models\Meeting::class)) {
            $out[] = ['label' => __('d3_look.qa_calendar'), 'url' => route('extranet.calendar', ['locale' => $locale])];
        }
        if ($gate->allows('viewAny', Folder::class)) {
            $out[] = ['label' => __('d3_look.qa_folders'), 'url' => route('extranet.folders', ['locale' => $locale])];
        }
        $out[] = ['label' => __('d3_look.qa_notices'), 'url' => route('announcements', ['locale' => $locale])];
        $out[] = ['label' => __('d3_look.qa_notification_settings'), 'url' => route('notifications', ['locale' => $locale]).'#preferences'];

        return $out;
    }
}

<?php

namespace App\Support;

use App\Enums\Clearance;
use App\Enums\PageStatus;
use App\Enums\Permission;
use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\Meeting;
use App\Models\NewsItem;
use App\Models\Page;
use App\Models\Publication;
use App\Models\SocialPost;
use App\Models\User;
use App\Notifications\ApprovalActivity;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moderated publishing, F2.
 *
 * States, as the author reads them: Draft, In review, Published, Returned.
 * Published is the item's own public flag (page and news status,
 * publication is_public, a meeting's public clearance). In review and
 * Returned come from the newest row in approvals. Everything else is Draft.
 *
 * Four eyes: nobody approves an item they sent for approval, whatever their
 * role. Each step writes an audit row and an in-app notification.
 *
 * Public for other workers: awaitingCountFor(User) is the number on the
 * sidebar badge and the dashboard block; awaitingFor(User) is the list.
 *
 * Evening pass: approving is a permission per content type (approve
 * publications, news, events, pages, social posts). The inbox and the count
 * hold only the types this person may approve. An item In review is locked:
 * nobody edits it until it is approved or returned; an administrator may
 * withdraw it to Draft, which is audited.
 */
final class Approvals
{
    public const DRAFT = 'draft';

    public const IN_REVIEW = 'in_review';

    public const PUBLISHED = 'published';

    public const RETURNED = 'returned';

    /** @var array<string, class-string<Model>> */
    public const TYPES = [
        'publications' => Publication::class,
        'news' => NewsItem::class,
        'events' => Meeting::class,
        'pages' => Page::class,
        'social' => SocialPost::class,
    ];

    /* ------------------------------------------------------------------ */
    /* Reading                                                              */
    /* ------------------------------------------------------------------ */

    /** How many items wait for this person's decision. Zero for anyone who may not approve. */
    public static function awaitingCountFor(?User $user): int
    {
        if ($user === null || ! $user->hasPermission(Permission::ApproveContent) || ! self::ready()) {
            return 0;
        }

        return Approval::awaiting()
            ->whereIn('approvable_type', self::morphTypesFor($user))
            ->where(fn ($q) => $q->whereNull('submitted_by')->orWhere('submitted_by', '!=', $user->id))
            ->count();
    }

    /** @return Collection<int, Approval> */
    public static function awaitingFor(User $user, int $limit = 50): Collection
    {
        if (! $user->hasPermission(Permission::ApproveContent) || ! self::ready()) {
            return collect();
        }

        return Approval::awaiting()->with(['approvable', 'submitter'])
            ->whereIn('approvable_type', self::morphTypesFor($user))
            ->orderBy('submitted_at')->limit($limit)->get();
    }

    /** Whether this person holds the approve permission for one content type. */
    public static function mayApproveType(User $user, string $type): bool
    {
        $permission = Permission::approveFor($type);

        return $permission !== null && $user->hasPermission($permission);
    }

    /**
     * The stored approvable_type values of the types this person may approve.
     *
     * @return list<string>
     */
    public static function morphTypesFor(User $user): array
    {
        $out = [];

        foreach (self::TYPES as $type => $class) {
            if (self::mayApproveType($user, $type)) {
                $out[] = (new $class)->getMorphClass();
            }
        }

        return $out;
    }

    /** The content type key of a request, from its stored morph type. */
    public static function typeOfApproval(Approval $approval): ?string
    {
        foreach (self::TYPES as $type => $class) {
            if ((new $class)->getMorphClass() === $approval->approvable_type) {
                return $type;
            }
        }

        return null;
    }

    /**
     * An item In review is locked: its content may not change while a
     * moderator reads it. It opens again when approved, returned or
     * withdrawn by an administrator.
     */
    public static function isLocked(Model $model): bool
    {
        return self::typeOf($model) !== null && self::pending($model) !== null;
    }

    /**
     * For a save on a locked item: back to the editor with the reason
     * beside the approval panel, instead of a bare 403. The policies refuse
     * the same save, so this is the friendly answer, not the only check.
     */
    public static function refuseIfLocked(?Model $model): void
    {
        if ($model !== null && self::isLocked($model)) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                back()->withErrors(['approval' => __('evening.locked_refused')])
            );
        }
    }

    /** Administrators (holders of "publish without approval") may withdraw an item in review to Draft. */
    public static function mayWithdraw(User $user, Approval $approval): bool
    {
        return $approval->isOpen() && $user->hasPermission(Permission::PublishWithoutApproval);
    }

    /** Items this person sent that came back, with the comment. @return Collection<int, Approval> */
    public static function returnedTo(User $user, int $limit = 20): Collection
    {
        if (! self::ready()) {
            return collect();
        }

        return Approval::where('state', Approval::RETURNED)->where('submitted_by', $user->id)
            ->with(['approvable', 'decider'])->latest('decided_at')->limit($limit)->get()
            ->filter(fn (Approval $a) => $a->approvable !== null && self::latest($a->approvable)?->is($a));
    }

    public static function typeOf(Model $model): ?string
    {
        $key = array_search($model::class, self::TYPES, true);

        return $key === false ? null : $key;
    }

    public static function requiresApproval(string $type): bool
    {
        if (! self::ready()) {
            return false;
        }

        $mode = DB::table('approval_rules')->where('content_type', $type)->value('mode');

        return ($mode ?? 'one') === 'one';
    }

    public static function latest(Model $model): ?Approval
    {
        if (! self::ready()) {
            return null;
        }

        return Approval::where('approvable_type', $model->getMorphClass())
            ->where('approvable_id', $model->getKey())
            ->latest('id')->first();
    }

    public static function pending(Model $model): ?Approval
    {
        $latest = self::latest($model);

        return $latest?->isOpen() ? $latest : null;
    }

    public static function isPublic(Model $model): bool
    {
        return match (true) {
            $model instanceof Page => $model->status === PageStatus::Published,
            $model instanceof NewsItem => $model->status === 'published',
            $model instanceof Publication => (bool) $model->is_public,
            $model instanceof Meeting => $model->clearance === Clearance::Public,
            $model instanceof SocialPost => $model->status === 'published',
            default => false,
        };
    }

    public static function state(Model $model): string
    {
        $latest = self::latest($model);

        if ($latest?->isOpen()) {
            return self::IN_REVIEW;
        }

        if (self::isPublic($model)) {
            return self::PUBLISHED;
        }

        return $latest?->state === Approval::RETURNED ? self::RETURNED : self::DRAFT;
    }

    /**
     * Whether this person may make the item public in one step, without a
     * moderator. Where the type's rule is "one approval" only holders of
     * "publish without approval" may (administrators); where it is "no
     * approval" anyone who may publish content may, as before Day 2.
     */
    public static function mayPublishDirectly(User $user, Model|string $model): bool
    {
        $type = is_string($model) ? $model : self::typeOf($model);

        if ($type !== null && self::requiresApproval($type)) {
            return $user->hasPermission(Permission::PublishWithoutApproval);
        }

        return $user->hasPermission($type === 'events' ? Permission::ManageMeetings : Permission::PublishContent);
    }

    public static function maySubmit(User $user, Model $model): bool
    {
        return $user->hasPermission(self::typeOf($model) === 'events' ? Permission::ManageMeetings : Permission::ManageContent);
    }

    /* ------------------------------------------------------------------ */
    /* Transitions                                                          */
    /* ------------------------------------------------------------------ */

    public static function submit(Model $model, User $by, ?string $note = null): Approval
    {
        if (self::typeOf($model) === null) {
            throw new DomainException(__('fa.approval_not_moderated'));
        }

        if (! self::maySubmit($by, $model)) {
            throw new DomainException(__('fa.approval_may_not_submit'));
        }

        if (self::pending($model) !== null) {
            throw new DomainException(__('fa.approval_already_waiting'));
        }

        $approval = DB::transaction(function () use ($model, $by, $note) {
            $approval = Approval::create([
                'approvable_type' => $model->getMorphClass(),
                'approvable_id' => $model->getKey(),
                'state' => Approval::IN_REVIEW,
                'submitted_by' => $by->id,
                'submitted_at' => now(),
                'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
                'snapshot' => self::snapshot($model),
            ]);

            if ($model instanceof Page && $model->status !== PageStatus::Published) {
                $model->update(['status' => PageStatus::Review]);
            }

            if ($model instanceof NewsItem && $model->status !== 'published') {
                $model->update(['status' => 'review']);
            }

            AuditLog::record('approval_submitted', $model, ['approval_id' => $approval->id, 'note' => $approval->note], $by);

            return $approval;
        });

        self::notify(
            self::approvers(self::typeOf($model))->reject(fn (User $u) => $u->is($by)),
            'approval_submitted', $approval, $model, $by
        );

        return $approval;
    }

    /** Four eyes: the decider may not be the author. */
    public static function mayDecide(User $user, Approval $approval): bool
    {
        $type = self::typeOfApproval($approval);

        return $approval->isOpen()
            && $type !== null && self::mayApproveType($user, $type)
            && $approval->submitted_by !== $user->id;
    }

    public static function approve(Approval $approval, User $by, ?string $comment = null): Approval
    {
        self::assertMayDecide($approval, $by);

        $model = $approval->approvable;

        DB::transaction(function () use ($approval, $by, $comment, $model) {
            self::publish($model);

            $approval->update([
                'state' => Approval::APPROVED,
                'decided_by' => $by->id,
                'decided_at' => now(),
                'comment' => $comment !== null && trim($comment) !== '' ? trim($comment) : null,
            ]);

            AuditLog::record('approval_approved', $model, ['approval_id' => $approval->id, 'comment' => $approval->comment], $by);
        });

        self::notify(collect([$approval->submitter])->filter(), 'approval_approved', $approval, $model, $by);

        // Item 1b: the other languages are drafted by machine (App\Listeners\QueueMachineTranslations).
        event(new \App\Events\ContentApproved($model, $approval, $by->id));

        return $approval;
    }

    public static function return(Approval $approval, User $by, string $comment): Approval
    {
        self::assertMayDecide($approval, $by);

        if (trim($comment) === '') {
            throw new DomainException(__('fa.approval_comment_required'));
        }

        $model = $approval->approvable;

        DB::transaction(function () use ($approval, $by, $comment, $model) {
            if ($model instanceof Page && $model->status === PageStatus::Review) {
                $model->update(['status' => PageStatus::Draft]);
            }

            if ($model instanceof NewsItem && $model->status === 'review') {
                $model->update(['status' => 'draft']);
            }

            $approval->update([
                'state' => Approval::RETURNED,
                'decided_by' => $by->id,
                'decided_at' => now(),
                'comment' => trim($comment),
            ]);

            AuditLog::record('approval_returned', $model, ['approval_id' => $approval->id, 'comment' => trim($comment)], $by);
        });

        self::notify(collect([$approval->submitter])->filter(), 'approval_returned', $approval, $model, $by);

        return $approval;
    }

    /**
     * An administrator takes an item out of review: back to Draft, nobody
     * decided it. Audited, and the author is told.
     */
    public static function withdraw(Approval $approval, User $by, ?string $comment = null): Approval
    {
        if (! $approval->isOpen()) {
            throw new DomainException(__('fa.approval_already_decided'));
        }

        if (! self::mayWithdraw($by, $approval)) {
            throw new DomainException(__('evening.withdraw_refused'));
        }

        $model = $approval->approvable;
        $comment = $comment !== null && trim($comment) !== '' ? trim($comment) : null;

        DB::transaction(function () use ($approval, $by, $comment, $model) {
            if ($model instanceof Page && $model->status === PageStatus::Review) {
                $model->update(['status' => PageStatus::Draft]);
            }

            if ($model instanceof NewsItem && $model->status === 'review') {
                $model->update(['status' => 'draft']);
            }

            $approval->update([
                'state' => Approval::WITHDRAWN,
                'decided_by' => $by->id,
                'decided_at' => now(),
                'comment' => $comment ?? __('evening.withdrawn_comment'),
            ]);

            AuditLog::record('approval_withdrawn', $model, ['approval_id' => $approval->id, 'comment' => $comment], $by);
        });

        if ($approval->submitter !== null && ! $approval->submitter->is($by)) {
            self::notify(collect([$approval->submitter]), 'approval_withdrawn', $approval, $model, $by);
        }

        return $approval;
    }

    /**
     * Record a direct publication by someone allowed to skip the moderator,
     * where the type's rule asks for one approval. Writes the audit row and
     * tells the other administrators (F10). Call after the item is public.
     */
    public static function recordDirectPublish(Model $model, User $by): void
    {
        $type = self::typeOf($model);

        if ($type === null) {
            return;
        }

        if (! self::requiresApproval($type)) {
            AuditLog::record('published', $model, ['type' => $type, 'rule' => 'none'], $by);

            return;
        }

        $pending = self::pending($model);
        $pending?->update(['state' => Approval::WITHDRAWN, 'decided_by' => $by->id, 'decided_at' => now(),
            'comment' => __('fa.approval_superseded')]);

        AuditLog::record('published_without_approval', $model, ['type' => $type, 'rule' => 'one'], $by);

        AdminAlerts::send($by, 'published_without_approval', [
            'subject' => self::title($model),
            'type' => __('fa.type_'.$type),
        ], self::adminUrl($model));
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                              */
    /* ------------------------------------------------------------------ */

    public static function publish(Model $model): void
    {
        match (true) {
            $model instanceof Page => $model->update([
                'status' => PageStatus::Published,
                'published_at' => $model->published_at ?? now(),
            ]),
            $model instanceof NewsItem => $model->update([
                'status' => 'published',
                'published_at' => $model->published_at ?? now(),
            ]),
            $model instanceof Publication => $model->update(['is_public' => true]),
            $model instanceof Meeting => $model->update(['clearance' => Clearance::Public]),
            $model instanceof SocialPost => $model->update([
                'status' => 'published',
                'published_at' => $model->published_at ?? now(),
            ]),
            default => throw new DomainException(__('fa.approval_not_moderated')),
        };
    }

    /**
     * Who may approve: of one type when given, of any type otherwise.
     *
     * @return Collection<int, User>
     */
    public static function approvers(?string $type = null): Collection
    {
        return User::query()->withoutGlobalScopes()->where('status', 'active')->get()
            ->filter(fn (User $u) => $type !== null ? self::mayApproveType($u, $type) : $u->hasPermission(Permission::ApproveContent))
            ->values();
    }

    public static function title(?Model $model, ?string $locale = null): string
    {
        if ($model === null) {
            return __('fa.former_item');
        }

        if ($model instanceof SocialPost) {
            return \Illuminate\Support\Str::limit((string) ($model->caption ?: $model->url), 80);
        }

        if (method_exists($model, 'translated')) {
            $title = $model->translated('title', '', $locale);

            if ($title !== '') {
                return $title;
            }
        }

        return (string) ($model->getAttribute('title') ?? $model->getAttribute('slug') ?? '#'.$model->getKey());
    }

    public static function adminUrl(?Model $model): string
    {
        $locale = app()->getLocale();

        return match (true) {
            $model instanceof Page => route('admin.pages.edit', ['locale' => $locale, 'page' => $model->slug]),
            $model instanceof NewsItem => route('admin.resources.edit', ['locale' => $locale, 'resource' => 'news', 'id' => $model->id]),
            $model instanceof Publication => route('admin.resources.edit', ['locale' => $locale, 'resource' => 'publications', 'id' => $model->id]),
            $model instanceof SocialPost => route('admin.social.edit', ['locale' => $locale, 'post' => $model->id]),
            $model instanceof Meeting && \Illuminate\Support\Facades\Route::has('admin.meetings.edit') => route('admin.meetings.edit', ['locale' => $locale, 'meeting' => $model->slug]),
            default => route('admin.approvals', ['locale' => $locale]),
        };
    }

    /**
     * What the moderator compares: the attributes and every translation at
     * the moment of submission.
     *
     * @return array<string, mixed>
     */
    public static function snapshot(Model $model): array
    {
        $fields = [];

        foreach ($model->attributesToArray() as $key => $value) {
            if (in_array($key, ['id', 'created_at', 'updated_at', 'deleted_at', 'synced_at'], true) || is_array($value)) {
                continue;
            }

            $fields[$key] = is_scalar($value) || $value === null ? $value : (string) json_encode($value);
        }

        $translations = [];

        if (method_exists($model, 'translations')) {
            foreach ($model->translations()->get() as $row) {
                $translations[$row->field.'.'.$row->locale] = $row->value;
            }
        }

        if ($model instanceof Page) {
            foreach ($model->blocks()->with('translations')->get() as $block) {
                foreach ($block->translations as $row) {
                    $translations['block '.$block->position.' '.$row->field.'.'.$row->locale] = $row->value;
                }
            }
        }

        return ['fields' => $fields, 'translations' => $translations];
    }

    /**
     * The differences between this submission and the last approved one.
     *
     * @return list<array{key: string, before: ?string, after: ?string}>
     */
    public static function changes(Approval $approval): array
    {
        $previous = Approval::where('approvable_type', $approval->approvable_type)
            ->where('approvable_id', $approval->approvable_id)
            ->where('state', Approval::APPROVED)->where('id', '<', $approval->id)
            ->latest('id')->first();

        $before = self::flatten($previous?->snapshot ?? []);
        $after = self::flatten($approval->snapshot ?? []);
        $out = [];

        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $key) {
            $b = $before[$key] ?? null;
            $a = $after[$key] ?? null;

            if ((string) $b !== (string) $a) {
                $out[] = ['key' => $key, 'before' => $b === null ? null : (string) $b, 'after' => $a === null ? null : (string) $a];
            }
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private static function flatten(array $snapshot): array
    {
        return array_merge($snapshot['fields'] ?? [], $snapshot['translations'] ?? []);
    }

    private static function assertMayDecide(Approval $approval, User $by): void
    {
        if (! $approval->isOpen()) {
            throw new DomainException(__('fa.approval_already_decided'));
        }

        if ($approval->submitted_by === $by->id) {
            throw new DomainException(__('fa.approval_four_eyes'));
        }

        $type = self::typeOfApproval($approval);

        if ($type === null || ! self::mayApproveType($by, $type)) {
            throw new DomainException(__('fa.approval_may_not_decide'));
        }

        if ($approval->approvable === null) {
            throw new DomainException(__('fa.approval_item_gone'));
        }
    }

    /** @param Collection<int, User|null> $recipients */
    private static function notify(Collection $recipients, string $event, Approval $approval, ?Model $model, User $actor): void
    {
        // Through the one notifier: only people who may open the request
        // are told, each in their own language, and never twice.
        Notifier::send(
            $recipients,
            fn (User $user) => new ApprovalActivity(
                $event,
                self::title($model, $user->preferred_locale ?? 'en'),
                route('admin.approvals.show', ['locale' => Locales::isSupported($user->preferred_locale) ? $user->preferred_locale : Locales::FALLBACK, 'approval' => $approval->id]),
                $actor->name,
                $approval->comment,
            ),
            $event === 'approval_submitted' ? 'approval_request' : 'decision',
            'approval:'.$approval->id.':'.$event,
            fn (User $user) => \Illuminate\Support\Facades\Gate::forUser($user)->allows('enter-back-office')
                && \Illuminate\Support\Facades\Gate::forUser($user)->allows('view', $approval),
        );
    }

    private static function ready(): bool
    {
        static $ready = [];
        $key = spl_object_id(app());

        return $ready[$key] ??= Schema::hasTable('approvals') && Schema::hasTable('approval_rules');
    }
}

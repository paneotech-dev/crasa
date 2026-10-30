<?php

namespace App\Support;

use App\Enums\PageStatus;
use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Day 3, editor flow (item 1a).
 *
 * An editor (anyone who may not publish a type directly) never chooses a
 * status: the forms offer "Save draft" and "Save and send for approval",
 * and a status posted anyway is dropped on the server.
 *
 * Editing a published item does not touch the live row. The change is held
 * as a revision on an approval row: state "draft" while the editor works on
 * it, "in_review" once sent. Approving writes the revision to the live row
 * in the same transaction, so the public page shows the old text until the
 * moment the new one is approved and never shows nothing.
 *
 * A revision is {attributes: {column: value}, translations: {locale: {field: value}}}.
 */
final class EditorFlow
{
    /** The status control each moderated type carries on its form, if any. */
    public const STATUS_FIELDS = [
        'news' => 'status',
        'publications' => 'is_public',
        'events' => 'clearance',
        'social' => 'status',
        'pages' => 'status',
    ];

    /** Whether this person works through the two buttons rather than a status. */
    public static function applies(?User $user, Model|string $model): bool
    {
        $type = is_string($model) ? $model : Approvals::typeOf($model);

        return $user !== null && $type !== null && isset(Approvals::TYPES[$type])
            && ! Approvals::mayPublishDirectly($user, $type);
    }

    /** Whether a save by this person must be held as a revision instead of written live. */
    public static function holds(?User $user, Model $model): bool
    {
        return $model->exists && Approvals::isPublic($model) && self::applies($user, $model);
    }

    /** The revision the editor is working on, sent, or had returned. */
    public static function pendingFor(Model $model): ?Approval
    {
        $latest = Approvals::latest($model);

        return $latest !== null && $latest->revision !== null
            && in_array($latest->state, [Approval::DRAFT, Approval::IN_REVIEW, Approval::RETURNED], true)
            ? $latest : null;
    }

    /** Save the revision as a draft: the live row and the public page do not change. */
    public static function saveDraft(Model $model, User $by, array $revision): Approval
    {
        return DB::transaction(function () use ($model, $by, $revision) {
            $latest = Approvals::latest($model);
            $values = [
                'revision' => $revision,
                'snapshot' => self::snapshot($model, $revision),
                'submitted_by' => $by->id,
                'submitted_at' => now(),
            ];

            if ($latest !== null && $latest->state === Approval::DRAFT) {
                $latest->update($values);
                $approval = $latest;
            } else {
                $approval = Approval::create($values + [
                    'approvable_type' => $model->getMorphClass(),
                    'approvable_id' => $model->getKey(),
                    'state' => Approval::DRAFT,
                ]);
            }

            AuditLog::record('revision_saved', $model, ['approval_id' => $approval->id], $by);

            return $approval;
        });
    }

    /** What the moderator compares: the item as it would read once approved. */
    public static function snapshot(Model $model, array $revision): array
    {
        $snapshot = Approvals::snapshot($model);

        foreach ($revision['attributes'] ?? [] as $key => $value) {
            $snapshot['fields'][$key] = is_scalar($value) || $value === null ? $value : (string) json_encode($value);
        }

        foreach ($revision['translations'] ?? [] as $locale => $fields) {
            foreach ((array) $fields as $field => $value) {
                $snapshot['translations'][$field.'.'.$locale] = $value;
            }
        }

        return $snapshot;
    }

    /** Write an approved revision to the live row. Called inside the approval's transaction. */
    public static function apply(Approval $approval, Model $model, User $by): void
    {
        $revision = $approval->revision ?? [];

        if ($model instanceof Page) {
            app(\App\Services\PageVersioning::class)->recordInitial($model);
        }

        if (($attributes = $revision['attributes'] ?? []) !== []) {
            $model->fill($attributes)->save();
        }

        foreach ($revision['translations'] ?? [] as $locale => $fields) {
            foreach ((array) $fields as $field => $value) {
                $model->setTranslation($field, $locale, $value === null || trim((string) $value) === '' ? null : (string) $value, $approval->submitted_by);
            }
        }

        if (method_exists($model, 'unsetRelation')) {
            $model->unsetRelation('translations');
        }

        $model->touch();

        if ($model instanceof Page) {
            app(\App\Services\PageVersioning::class)->record($model, __('admin.page_version_details'));
        }

        AuditLog::record('revision_applied', $model, ['approval_id' => $approval->id], $by);
    }

    /**
     * Show the held revision in the editor's form: attributes filled on the
     * model (not saved) and the translations returned for the view.
     *
     * @return array<string, array<string, ?string>> locale => field => value
     */
    public static function overlay(Model $model, ?User $user): array
    {
        if (! self::applies($user, $model) || ($pending = self::pendingFor($model)) === null) {
            return [];
        }

        $model->fill($pending->revision['attributes'] ?? []);

        return $pending->revision['translations'] ?? [];
    }

    /**
     * For a new item saved by an editor: the status the type starts in.
     *
     * @return array<string, mixed>
     */
    public static function draftDefaults(string $type): array
    {
        return match ($type) {
            'news', 'social' => ['status' => 'draft'],
            'publications' => ['is_public' => false],
            'events' => ['clearance' => \App\Enums\Clearance::Member],
            'pages' => ['status' => PageStatus::Draft],
            default => [],
        };
    }

    /**
     * After an editor's save of the live row: "Save and send for approval"
     * opens the request. Returns the message that says what happens next.
     */
    public static function afterSave(Request $request, Model $model): string
    {
        $send = $request->input('intent') === 'send';

        if ($send) {
            try {
                Approvals::submit($model, $request->user(), self::note($request));
            } catch (\DomainException $e) {
                throw ValidationException::withMessages(['approval' => $e->getMessage()]);
            }
        }

        return self::message($send, false);
    }

    /**
     * An editor's save of a published item: held as a revision, sent when
     * asked. The live row is not written. Returns the message.
     */
    public static function hold(Request $request, Model $model, array $revision): string
    {
        $send = $request->input('intent') === 'send';

        if (! $send) {
            self::saveDraft($model, $request->user(), $revision);

            return self::message(false, true);
        }

        try {
            Approvals::submit($model, $request->user(), self::note($request), $revision);
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['approval' => $e->getMessage()]);
        }

        return self::message(true, true);
    }

    private static function note(Request $request): ?string
    {
        $note = $request->input('note');

        return is_string($note) ? mb_substr($note, 0, 2000) : null;
    }

    /** The message after a save, saying what happens next. */
    public static function message(bool $sent, bool $held): string
    {
        return match (true) {
            $sent && $held => __('m3_edflow.sent_revision'),
            $sent => __('m3_edflow.sent'),
            $held => __('m3_edflow.saved_revision'),
            default => __('m3_edflow.saved_draft'),
        };
    }
}

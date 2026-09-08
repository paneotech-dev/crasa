<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

/**
 * The audit trail. Append only.
 *
 * A row is never updated and never deleted. That is enforced here, on the
 * model, rather than left to convention: an audit trail a developer can
 * quietly rewrite is not an audit trail. Purging for retention, if it is
 * ever agreed, is a deliberate database operation, not an Eloquent delete.
 */
class AuditLog extends Model
{
    // Content
    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const DELETED = 'deleted';

    // Actions with no model event to hang off
    public const LOGIN = 'login';

    public const LOGIN_FAILED = 'login_failed';

    public const LOGOUT = 'logout';

    public const ROLE_CHANGED = 'role_changed';

    public const DOCUMENT_DOWNLOADED = 'document_downloaded';

    public const SUBMISSION_TRANSITIONED = 'submission_transitioned';

    protected $table = 'audit_log';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'action', 'auditable_type', 'auditable_id', 'changes', 'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new RuntimeException('Audit rows cannot be updated.');
        });

        static::deleting(function (): never {
            throw new RuntimeException('Audit rows cannot be deleted.');
        });

        static::saving(function (AuditLog $entry): void {
            if ($entry->exists) {
                throw new RuntimeException('Audit rows cannot be updated.');
            }

            $entry->created_at ??= now();
        });
    }

    /**
     * Record an action. Model events use this, and so does anything without
     * a model event: login, failed login, logout, a role change, a download
     * of a non-public document, a submission state transition.
     *
     * @param  array<string, mixed>|null  $changes
     */
    public static function record(
        string $action,
        ?Model $subject = null,
        ?array $changes = null,
        int|User|null $user = null,
        ?string $ipAddress = null,
    ): self {
        $userId = match (true) {
            $user instanceof User => $user->getKey(),
            is_int($user) => $user,
            default => auth()->id(),
        };

        return static::create([
            'user_id' => $userId,
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'changes' => $changes,
            'ip_address' => $ipAddress ?? (app()->runningInConsole() ? null : request()->ip()),
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}

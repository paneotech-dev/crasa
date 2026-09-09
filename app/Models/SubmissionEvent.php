<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Every transition a return has made. Append only.
 *
 * CLAUDE.md: "Every transition writes to the audit log." This is the
 * submission's own trail, beside the platform-wide one, because the
 * question an auditor asks about a return is a sequence -- who sent it,
 * who returned it, what they said, when it came back -- and reconstructing
 * that from a general log is work nobody does.
 *
 * Updating and deleting throw, as they do on AuditLog. A trail somebody can
 * quietly rewrite is not a trail.
 */
class SubmissionEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['submission_id', 'from_status', 'to_status', 'actor_id', 'note', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('A submission event is a record of something that happened. It cannot be edited.');
        });

        static::deleting(function (): void {
            throw new RuntimeException('A submission event cannot be deleted. The trail is the point of it.');
        });
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

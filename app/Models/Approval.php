<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One request to publish one item (F2). A row is opened when the author
 * sends the item for approval and closed when a moderator approves or
 * returns it; the next submission opens a new row, so the table is the
 * item's whole moderation history.
 */
class Approval extends Model
{
    public const IN_REVIEW = 'in_review';

    public const APPROVED = 'approved';

    public const RETURNED = 'returned';

    public const WITHDRAWN = 'withdrawn';

    /**
     * Day 3: changes to a published item, saved by an editor and not yet
     * sent. Not open, never in the inbox; the live row is untouched.
     */
    public const DRAFT = 'draft';

    protected $fillable = [
        'approvable_type', 'approvable_id', 'state', 'submitted_by', 'submitted_at',
        'decided_by', 'decided_at', 'note', 'comment', 'snapshot', 'revision',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
            'snapshot' => 'array',
            'revision' => 'array',
        ];
    }

    public function approvable(): MorphTo
    {
        return $this->morphTo()->withoutGlobalScopes();
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by')->withoutGlobalScopes();
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by')->withoutGlobalScopes();
    }

    public function scopeAwaiting(Builder $query): Builder
    {
        return $query->where('state', self::IN_REVIEW);
    }

    public function isOpen(): bool
    {
        return $this->state === self::IN_REVIEW;
    }
}

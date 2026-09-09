<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Secretariat comment against one field of a return.
 *
 * Screen 06: "Comments are attached to specific fields, not to the
 * submission as a whole, so you can go straight to what needs changing."
 * A returned submission with one comment on the whole form makes a
 * representative re-read twelve indicators to find the one at issue.
 */
class SubmissionFieldComment extends Model
{
    use Auditable;

    protected $fillable = ['submission_id', 'indicator_id', 'author_id', 'comment', 'resolved'];

    protected function casts(): array
    {
        return ['resolved' => 'boolean'];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}

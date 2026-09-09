<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One figure inside a return, before it is published.
 *
 * It is not an IndicatorValue and it never becomes one by being edited: a
 * validated submission writes into indicator_values, which is the moment a
 * figure becomes readable by the region.
 */
class SubmissionValue extends Model
{
    use Auditable;

    protected $fillable = [
        'submission_id', 'indicator_id', 'value_numeric', 'value_text', 'currency', 'source_note',
    ];

    protected function casts(): array
    {
        return ['value_numeric' => 'decimal:4'];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class);
    }

    public function hasFigure(): bool
    {
        return $this->value_numeric !== null || filled($this->value_text);
    }

    /**
     * A figure with no source note is not complete.
     *
     * Screen 06 draws the empty source field with a red rule and the words
     * "Source note required". The rule is not decoration: when a member
     * state queries a published figure, the source is the answer, and there
     * is no way to reconstruct it afterwards.
     */
    public function isComplete(): bool
    {
        return $this->hasFigure() && filled($this->source_note);
    }
}

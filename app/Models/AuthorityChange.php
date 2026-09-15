<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\CountryScoped;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member state's proposed change to its own authority record.
 *
 * COUNTRY-SCOPED AS A GLOBAL SCOPE, like submissions. A representative
 * reaches their own member state's proposals and no others, and that holds
 * in a controller, a job, a report and anything written later by somebody
 * who has not read this comment.
 */
class AuthorityChange extends Model
{
    use Auditable, CountryScoped;

    protected $fillable = [
        'authority_id', 'country_id', 'proposed_by', 'reviewed_by',
        'payload', 'status', 'submitted_at', 'reviewed_at', 'review_note',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function authority(): BelongsTo
    {
        return $this->belongsTo(Authority::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'submitted');
    }

    public function isOpen(): bool
    {
        return $this->status === 'submitted';
    }
}

<?php

namespace App\Models;

use App\Enums\Clearance;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\MarksSampleData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A meeting in the calendar, screens 04 and 12.
 *
 * THE BOUNDARY THIS MODEL SITS ON. The platform records what a meeting is,
 * when it is, and who owns it, and links out to wherever a delegate
 * registers. It records nothing about transfers, hotels, interpretation,
 * catering or feedback: the website displays, the Event Management Platform
 * manages, and that division is a separate commercial agreement rather than
 * a design preference.
 *
 * If a column for a driver or a room block ever appears here, that line has
 * been crossed.
 */
class Meeting extends Model
{
    use Auditable, HasFactory, MarksSampleData;

    protected $fillable = [
        'committee_id', 'title', 'slug', 'kind', 'starts_on', 'ends_on',
        'location', 'agenda', 'clearance', 'registration_url', 'is_sample',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'clearance' => Clearance::class,
            'is_sample' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function committee(): BelongsTo
    {
        return $this->belongsTo(Committee::class);
    }

    public function documents(): HasMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /** Who has filed an arrival and a departure against this meeting. */
    public function delegateRegistrations(): HasMany
    {
        return $this->hasMany(DelegateRegistration::class);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereDate('starts_on', '>=', now()->toDateString())->orderBy('starts_on');
    }

    public function scopePast(Builder $query): Builder
    {
        return $query->whereDate('starts_on', '<', now()->toDateString())->orderByDesc('starts_on');
    }

    /** Public meetings only, for the events section. */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('clearance', Clearance::Public);
    }
}

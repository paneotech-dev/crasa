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
        'committee_id', 'title', 'slug', 'kind', 'category', 'starts_on', 'ends_on',
        'location', 'venue', 'venue_country', 'agenda', 'summary', 'clearance',
        'registration_url', 'external_id', 'synced_at', 'is_sample',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'clearance' => Clearance::class,
            'synced_at' => 'datetime',
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

    /**
     * RP-05: a meeting scheduled or changed.
     *
     * WHY THIS FIRES ON THE MODEL RATHER THAN IN A CONTROLLER. There is no
     * controller: meetings arrive from the events host, because the website
     * displays and the Event Management Platform manages. A change reaches
     * this platform only through the sync.
     *
     * WHY IT DOES NOT FIRE ON THE FIRST SYNC. Importing forty-three
     * meetings would notify every committee about every meeting it already
     * knew about. A meeting that has been synced before and whose date or
     * place has moved is the actual event, and it is the only one anybody
     * wants to hear about.
     *
     * This tells people that a meeting exists or has moved. It arranges
     * nothing, reminds nobody to travel, and there is no trigger in the
     * enum for anything that would.
     */
    protected static function booted(): void
    {
        static::updating(function (Meeting $meeting): void {
            if ($meeting->committee_id === null || $meeting->getOriginal('synced_at') === null) {
                return;
            }

            if (! $meeting->isDirty(['starts_on', 'ends_on', 'venue', 'location'])) {
                return;
            }

            app(\App\Services\NotifiesMembers::class)->fire(
                \App\Enums\NotificationTrigger::MeetingChanged,
                __('notifications.meeting_moved', [
                    'title' => $meeting->title,
                    'date' => $meeting->starts_on?->isoFormat('D MMMM YYYY') ?? '',
                ]),
                route('extranet.calendar', ['locale' => 'en']),
                committee: $meeting->committee,
                payload: ['meeting_id' => $meeting->id],
            );
        });
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

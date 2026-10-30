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
        'committee_id', 'title', 'slug', 'kind', 'category', 'starts_on', 'ends_on', 'date_unknown',
        'location', 'venue', 'venue_country', 'agenda', 'summary', 'clearance',
        'registration_url', 'external_id', 'synced_at', 'is_sample',
        'committee_code', 'organiser', 'source', 'news_item_id', 'status', 'status_note',
        'starts_at', 'ends_at', 'timezone', 'online_url',
        'registration_opens_on', 'registration_closes_on', 'capacity',
    ];

    /** Statuses an event can carry on the public page. */
    public const STATUSES = ['scheduled', 'postponed', 'cancelled'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'date_unknown' => 'boolean',
            'clearance' => Clearance::class,
            'synced_at' => 'datetime',
            'is_sample' => 'boolean',
            'registration_opens_on' => 'date',
            'registration_closes_on' => 'date',
            'imported_original' => 'array',
            'capacity' => 'integer',
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

    /** The newsroom item that reports this meeting, where there is one. */
    public function newsItem(): BelongsTo
    {
        return $this->belongsTo(NewsItem::class);
    }

    /** The body that meets, with its colour and letter on the calendar. */
    public function group(): \App\Support\EventGroup
    {
        return \App\Support\EventGroup::for($this);
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isPostponed(): bool
    {
        return $this->status === 'postponed';
    }

    /**
     * The status a reader sees, worked out when it is shown rather than
     * stored: cancelled and postponed as recorded, otherwise held once the
     * last day has passed and scheduled before that. Nothing has to run on
     * the day after a meeting for it to stop reading "Scheduled".
     */
    public function displayStatus(): string
    {
        return match (true) {
            $this->isCancelled() => 'cancelled',
            $this->isPostponed() => 'postponed',
            $this->hasEnded() => 'held',
            default => 'scheduled',
        };
    }

    /**
     * The day by which the Secretariat answers a filing: the meeting's
     * first day, or for a meeting already running with its form still open,
     * the form's closing day (never a day already gone).
     */
    public function registrationReplyBy(): ?\Carbon\CarbonInterface
    {
        if (! $this->starts_on) {
            return null;
        }

        if ($this->starts_on->toDateString() > \App\Support\LocalTime::today()) {
            return $this->starts_on;
        }

        return $this->registration_closes_on ?? $this->lastDay();
    }

    /** The display status in the reader's language. */
    public function displayStatusLabel(): string
    {
        $status = $this->displayStatus();

        return $status === 'held' ? __('d3_people.status_held') : __('e.status_'.$status);
    }

    public function isOnline(): bool
    {
        return $this->online_url !== null && $this->online_url !== ''
            || strcasecmp((string) $this->location, 'Online') === 0;
    }

    /** The last day of the meeting, or its only day. */
    public function lastDay(): ?\Carbon\CarbonInterface
    {
        return $this->ends_on ?: $this->starts_on;
    }

    /**
     * Whether a delegate can still file an arrival and a departure: a dated
     * meeting that has not started (or has a closing date that has not
     * passed), is not cancelled, and is inside its registration window
     * where one is set.
     */
    public function registrationIsOpen(): bool
    {
        if ($this->date_unknown || ! $this->starts_on || $this->isCancelled()) {
            return false;
        }

        $today = \App\Support\LocalTime::today();

        // A meeting closes its form when it starts, unless the Secretariat
        // set a closing date: then the form stays open to that date, and
        // never past the meeting's last day (Day 3: a workshop whose
        // delegates register in the room).
        if ($this->starts_on->toDateString() <= $today
            && ! ($this->registration_closes_on && $this->lastDay()->toDateString() >= $today)) {
            return false;
        }

        if ($this->registration_opens_on && $this->registration_opens_on->toDateString() > $today) {
            return false;
        }

        return ! ($this->registration_closes_on && $this->registration_closes_on->toDateString() < $today);
    }

    /** "10 to 12 November 2026", "30 September to 3 October 2024", or one day. */
    public function dateRange(): string
    {
        if ($this->date_unknown || ! $this->starts_on) {
            return __('events.date_not_stated');
        }

        $start = $this->starts_on->copy()->locale(app()->getLocale());
        $end = $this->lastDay()->copy()->locale(app()->getLocale());

        if ($start->isSameDay($end)) {
            return $start->isoFormat('D MMMM YYYY');
        }

        $from = match (true) {
            $start->isSameMonth($end) => $start->isoFormat('D'),
            $start->isSameYear($end) => $start->isoFormat('D MMMM'),
            default => $start->isoFormat('D MMMM YYYY'),
        };

        return __('e.range', ['from' => $from, 'to' => $end->isoFormat('D MMMM YYYY')]);
    }

    /** "09:00 to 17:00 CAT", or null when no time is recorded. */
    public function timeRange(): ?string
    {
        if (! $this->starts_at) {
            return null;
        }

        $zone = $this->timezone ?: \App\Support\LocalTime::zone();
        $abbr = \Carbon\CarbonImmutable::parse($this->starts_on?->toDateString() ?? 'today', $zone)->format('T');
        $from = substr((string) $this->starts_at, 0, 5);
        $to = $this->ends_at ? substr((string) $this->ends_at, 0, 5) : null;

        return ($to ? __('e.range', ['from' => $from, 'to' => $to]) : $from).' '.$abbr;
    }

    /** Whether this meeting has ended, in the Secretariat's day. */
    public function hasEnded(): bool
    {
        return ! $this->date_unknown && $this->lastDay() !== null
            && $this->lastDay()->toDateString() < \App\Support\LocalTime::today();
    }

    public function documents(): \Illuminate\Database\Eloquent\Relations\MorphMany
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
        /*
         * Client brief item 7: a meeting moved or cancelled reaches that
         * committee's members and the people registered for it, through the
         * one notifier (App\Support\Notifier). An edit in the back office
         * counts as well as the sync; the first sync of a meeting still does
         * not, for the reason above.
         */
        static::updated(function (Meeting $meeting): void {
            if ($meeting->wasChanged('synced_at') && $meeting->getOriginal('synced_at') === null) {
                return;
            }

            $cancelled = $meeting->wasChanged('status') && $meeting->status === 'cancelled';
            $moved = $meeting->wasChanged(['starts_on', 'ends_on', 'starts_at', 'ends_at', 'venue', 'location', 'online_url'])
                || ($meeting->wasChanged('status') && $meeting->status === 'postponed');

            if (! $cancelled && ! $moved) {
                return;
            }

            \App\Support\Notifier::meetingChanged(
                $meeting,
                $cancelled ? 'cancelled' : 'changed',
                (string) $meeting->updated_at?->timestamp,
            );
        });
    }

    /**
     * A meeting whose date is not known is never upcoming: its stored date
     * is the day the legacy site was read, not the day it met.
     *
     * "Today" is the Secretariat's day (LocalTime), and a meeting that is
     * running is still upcoming until its last day has passed: a workshop
     * on its second day is not in the past.
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('date_unknown', false)
            ->whereRaw('COALESCE(ends_on, starts_on) >= ?', [\App\Support\LocalTime::today()])
            ->orderBy('starts_on');
    }

    /** Past meetings, newest first, and those with no stated date after them. */
    public function scopePast(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('date_unknown', true)
            ->orWhereRaw('COALESCE(ends_on, starts_on) < ?', [\App\Support\LocalTime::today()]))
            ->orderBy('date_unknown')
            ->orderByDesc('starts_on');
    }

    /** Meetings that can be placed on a calendar day. */
    public function scopeDated(Builder $query): Builder
    {
        return $query->where('date_unknown', false);
    }

    /** Public meetings only, for the events section. */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('clearance', Clearance::Public);
    }

    /** The place as a reader sees it: "Online" is said in the reader's language. */
    public function displayLocation(): ?string
    {
        return strcasecmp((string) $this->location, 'Online') === 0 ? __('e.online') : $this->location;
    }
}

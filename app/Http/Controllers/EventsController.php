<?php

namespace App\Http\Controllers;

use App\Enums\Clearance;
use App\Models\Meeting;
use App\Services\CalendarFeed;
use App\Support\EventGroup;
use App\Support\LocalTime;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * The public events section, E1 and E2.
 *
 * A month calendar, upcoming cards and past cards, all from one query.
 * A visitor sees public meetings. A signed-in reader also sees the meetings
 * their account may open (the validation workshop is for members), each
 * labelled with who can see it. Nothing here books travel or rooms: the
 * delegate form collects arrival and departure and nothing more.
 */
class EventsController extends Controller
{
    public function index(Request $request): View
    {
        $visible = $this->visible();
        $today = LocalTime::today();

        $groups = EventGroup::present($visible);
        $committee = strtoupper($request->string('committee')->toString());
        $committee = collect($groups)->contains(fn (EventGroup $g) => $g->code === $committee) ? $committee : null;

        $filtered = $committee
            ? $visible->filter(fn (Meeting $m) => $m->group()->code === $committee)->values()
            : $visible;

        $upcoming = $filtered
            ->filter(fn (Meeting $m) => ! $m->date_unknown && $m->lastDay()->toDateString() >= $today)
            ->sortBy(fn (Meeting $m) => $m->starts_on->toDateString())
            ->values();

        $past = $filtered
            ->reject(fn (Meeting $m) => $upcoming->contains($m))
            ->sortByDesc(fn (Meeting $m) => ($m->date_unknown ? '0' : '1').$m->starts_on?->toDateString())
            ->values();

        $pastByYear = $past->groupBy(fn (Meeting $m) => $m->date_unknown ? 'undated' : $m->starts_on->format('Y'));
        // The most recent year opens; "all" shows every year.
        $year = $request->string('year')->toString();
        $year = $year === 'all' ? null : ($pastByYear->has($year) ? $year : ($pastByYear->keys()->first() !== null ? (string) $pastByYear->keys()->first() : null));

        $month = $this->month($request->query('month'), $filtered, $today);
        $view = in_array($request->query('view'), ['month', 'list'], true) ? $request->query('view') : 'auto';

        return view('pages.events.index', [
            'upcoming' => $upcoming,
            'pastByYear' => $pastByYear,
            'year' => $year,
            'groups' => $groups,
            'committee' => $committee,
            'month' => $month,
            'weeks' => $this->weeks($month, $filtered),
            'monthEvents' => $filtered->filter(fn (Meeting $m) => $this->inMonth($m, $month))->sortBy(fn (Meeting $m) => $m->starts_on->toDateString())->values(),
            'today' => $today,
            'view' => $view,
            'counts' => [
                'upcoming' => $visible->filter(fn (Meeting $m) => ! $m->date_unknown && $m->lastDay()->toDateString() >= $today)->count(),
                'past' => $visible->filter(fn (Meeting $m) => $m->date_unknown || $m->lastDay()->toDateString() < $today)->count(),
            ],
        ]);
    }

    public function show(Meeting $meeting): View
    {
        abort_unless(Gate::allows('view', $meeting), 404);

        $meeting->load(['committee', 'newsItem', 'documents']);

        $dated = $this->visible()->reject(fn (Meeting $m) => $m->date_unknown)->values();
        $index = $dated->search(fn (Meeting $m) => $m->id === $meeting->id);

        return view('pages.events.show', [
            'meeting' => $meeting,
            'previous' => $index !== false && $index > 0 ? $dated[$index - 1] : null,
            'next' => $index !== false ? ($dated[$index + 1] ?? null) : null,
            'related' => $this->relatedNews($meeting),
        ]);
    }

    /**
     * One event as an iCalendar file, for "Add to my calendar".
     *
     * Dated meetings the reader may open only: a meeting with no stated
     * date has nothing to put in a calendar.
     */
    public function ics(Meeting $meeting, CalendarFeed $feed): Response
    {
        abort_unless(Gate::allows('view', $meeting) && ! $meeting->date_unknown && $meeting->starts_on, 404);

        $url = route('events.show', ['locale' => app()->getLocale(), 'meeting' => $meeting->slug]);

        return response($feed->single($meeting->load('committee'), $url), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="crasa-'.$meeting->slug.'.ics"',
        ]);
    }

    /** @return Collection<int, Meeting> every meeting this reader may see, oldest first */
    private function visible(): Collection
    {
        $signedIn = auth()->check();

        return Meeting::query()
            ->with(['committee', 'newsItem'])
            ->when(! $signedIn, fn ($q) => $q->where('clearance', Clearance::Public))
            ->orderBy('starts_on')
            ->get()
            ->filter(fn (Meeting $m) => $m->clearance === Clearance::Public || Gate::allows('view', $m))
            ->values();
    }

    /**
     * The month to show: the one asked for, else this month when it has a
     * meeting, else the next month that has one, else this month.
     */
    private function month(mixed $asked, Collection $meetings, string $today): CarbonImmutable
    {
        if (is_string($asked) && preg_match('/^(\d{4})-(\d{2})$/', $asked, $m) && (int) $m[2] >= 1 && (int) $m[2] <= 12) {
            return CarbonImmutable::create((int) $m[1], (int) $m[2], 1);
        }

        $current = CarbonImmutable::parse($today)->startOfMonth();

        if ($meetings->contains(fn (Meeting $m) => $this->inMonth($m, $current))) {
            return $current;
        }

        $next = $meetings
            ->filter(fn (Meeting $m) => ! $m->date_unknown && $m->starts_on->toDateString() > $today)
            ->sortBy(fn (Meeting $m) => $m->starts_on->toDateString())
            ->first();

        return $next ? CarbonImmutable::parse($next->starts_on->toDateString())->startOfMonth() : $current;
    }

    private function inMonth(Meeting $meeting, CarbonImmutable $month): bool
    {
        if ($meeting->date_unknown || ! $meeting->starts_on) {
            return false;
        }

        return $meeting->starts_on->toDateString() <= $month->endOfMonth()->toDateString()
            && $meeting->lastDay()->toDateString() >= $month->toDateString();
    }

    /**
     * Monday-first weeks covering the month, each day with its meetings.
     *
     * @return array<int, array<int, array{date: CarbonImmutable, inMonth: bool, meetings: Collection}>>
     */
    private function weeks(CarbonImmutable $month, Collection $meetings): array
    {
        $dated = $meetings->filter(fn (Meeting $m) => $this->inMonth($m, $month));
        $day = $month->startOfWeek(CarbonImmutable::MONDAY);
        $end = $month->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY);
        $weeks = [];

        while ($day <= $end) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $d = $day->toDateString();
                $week[] = [
                    'date' => $day,
                    'inMonth' => $day->month === $month->month,
                    'meetings' => $dated->filter(fn (Meeting $m) => $m->starts_on->toDateString() <= $d && $m->lastDay()->toDateString() >= $d)->values(),
                ];
                $day = $day->addDay();
            }
            $weeks[] = $week;
        }

        return $weeks;
    }

    /** The report of this meeting, then other news from the same weeks. */
    private function relatedNews(Meeting $meeting): Collection
    {
        $items = collect();

        if ($meeting->newsItem && $meeting->newsItem->status === 'published') {
            $items->push($meeting->newsItem);
        }

        if (! $meeting->date_unknown && $meeting->starts_on) {
            $from = $meeting->starts_on->copy()->subDays(14);
            $to = $meeting->lastDay()->copy()->addDays(45);
            \App\Models\NewsItem::published()
                ->whereBetween('published_at', [$from, $to])
                ->when($meeting->news_item_id, fn ($q, $id) => $q->where('id', '!=', $id))
                ->orderByDesc('published_at')->limit(3 - $items->count())->get()
                ->each(fn ($n) => $items->push($n));
        }

        return $items->take(3);
    }
}

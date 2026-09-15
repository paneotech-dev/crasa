<?php

namespace App\Support;

use App\Enums\SubmissionStatus;
use App\Models\Announcement;
use App\Models\Committee;
use App\Models\Document;
use App\Models\Meeting;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * What a member state sees when it signs in. RP-05, RP-12, screen 04.
 *
 * WHY THIS EXISTS. The extranet opened on a welcome and three panels of
 * recent things. A national regulator signing in wants one question
 * answered: what does my authority owe, and what has changed since I was
 * last here. Both are answerable from records the platform already holds,
 * and neither was being asked.
 *
 * ORDER IS THE DESIGN. Anything the Secretariat has returned comes first,
 * because it is the only item on the page that is blocked on this reader.
 * Then what is due. Then what has changed. A dashboard that sorts by
 * recency puts a document somebody filed this morning above a return that
 * was sent back three weeks ago.
 *
 * EVERY COUNT IS SCOPED THE SAME WAY THE PAGE BEHIND IT IS. Submissions
 * carry a country global scope, documents and meetings go through the Gate.
 * A dashboard is the classic place a permission layer leaks, because it
 * assembles from several sources and it is tempting to trust the query.
 */
final class CountryDashboard
{
    /**
     * @return array{
     *   country: ?\App\Models\Country,
     *   completeness: array{percent: int, rank: ?int, of: int},
     *   returned: Collection,
     *   due: Collection,
     *   documents: Collection,
     *   next_meeting: ?Meeting,
     *   announcements: Collection,
     *   committees: Collection
     * }
     */
    public static function for(User $user): array
    {
        return [
            'country' => $user->country,
            'completeness' => self::completeness($user),
            'returned' => self::returned($user),
            'due' => self::due($user),
            'documents' => self::documentsThisWeek($user),
            'next_meeting' => self::nextMeeting($user),
            'announcements' => self::announcements($user),
            'committees' => $user->committees()->withCount(['documents', 'meetings'])->orderBy('name')->get(),
        ];
    }

    /**
     * This state's completeness, and where it stands among the fourteen.
     *
     * The rank is here because a percentage on its own says nothing a
     * regulator can act on. Sixty per cent is good or bad depending on
     * whether the region is at forty or at ninety, and the comparison is
     * the reason the matrix exists.
     *
     * @return array{percent: int, rank: ?int, of: int}
     */
    public static function completeness(User $user): array
    {
        $matrix = CompletenessMatrix::build();
        $totals = collect($matrix['totals']);

        if ($user->country_id === null || ! $totals->has($user->country_id)) {
            return ['percent' => $matrix['overall'], 'rank' => null, 'of' => $totals->count()];
        }

        $mine = (int) $totals[$user->country_id]['percent'];

        /*
         * Rank counts states strictly ahead, so states on the same
         * percentage share a rank. Two states both on 60 are both third,
         * not third and fourth in whatever order the query returned them.
         */
        $ahead = $totals->filter(fn (array $row) => (int) $row['percent'] > $mine)->count();

        return ['percent' => $mine, 'rank' => $ahead + 1, 'of' => $totals->count()];
    }

    /** Returns the Secretariat has sent back. First on the page. */
    private static function returned(User $user): Collection
    {
        return Submission::query()
            ->where('status', SubmissionStatus::Returned)
            ->with('reviewer')
            ->orderByDesc('reviewed_at')
            ->get();
    }

    /**
     * Returns still open: a draft, or a period with no return at all.
     *
     * A period nobody has started is the case that matters most and is the
     * one a query over submissions cannot see, because there is no row. It
     * is derived from the reporting periods against what exists.
     */
    private static function due(User $user): Collection
    {
        if ($user->country_id === null) {
            return collect();
        }

        $existing = Submission::query()->pluck('status', 'period');

        return collect(ReportingPeriods::open())
            ->map(function (array $period) use ($existing): array {
                $status = $existing[$period['period']] ?? null;

                return $period + [
                    'status' => $status instanceof SubmissionStatus ? $status->value : ($status ?? 'not_started'),
                ];
            })
            ->reject(fn (array $period) => in_array($period['status'], ['submitted', 'validated'], true))
            ->values();
    }

    /**
     * Documents filed into this reader's committees in the last seven days.
     *
     * Through the Gate rather than through a join on clearance, because
     * clearance is per committee seat and an observer holds a different
     * grade from a member on the same committee.
     */
    private static function documentsThisWeek(User $user): Collection
    {
        $committees = $user->committees()->pluck('committees.id');

        if ($committees->isEmpty() && ! $user->seesAllCountries()) {
            return collect();
        }

        return Document::query()
            /*
             * The Secretariat holds no committee seat and still needs to see
             * what has been filed, so an account that sees every member
             * state gets the recent list rather than an empty panel. For a
             * member the panel means what its heading says: filed into a
             * committee this reader sits on.
             */
            ->when(
                $committees->isNotEmpty(),
                fn ($q) => $q->where('created_at', '>=', now()->subWeek())
                    ->where(fn ($w) => $w->whereIn('committee_id', $committees)
                        ->orWhereHasMorph('documentable', [Committee::class], fn ($c) => $c->whereIn('committees.id', $committees)))
            )
            ->with(['documentable', 'folder', 'uploader'])
            ->latest()
            ->limit(30)
            ->get()
            ->filter(fn (Document $document) => Gate::allows('view', $document))
            ->take(5)
            ->values();
    }

    private static function nextMeeting(User $user): ?Meeting
    {
        $committees = $user->committees()->pluck('committees.id');

        return Meeting::upcoming()
            ->with('committee')
            ->when(
                ! $user->seesAllCountries(),
                fn ($q) => $q->where(fn ($w) => $w->whereIn('committee_id', $committees)->orWhereNull('committee_id'))
            )
            ->get()
            ->first(fn (Meeting $meeting) => Gate::allows('view', $meeting));
    }

    private static function announcements(User $user): Collection
    {
        return Announcement::live()
            ->visibleTo($user)
            ->with(['translations', 'sector', 'committee'])
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();
    }
}

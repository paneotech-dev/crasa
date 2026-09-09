<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Committee;
use App\Models\Country;
use App\Models\Indicator;
use App\Models\Instrument;
use App\Models\NewsItem;
use App\Models\Publication;
use App\Support\Locales;
use App\Support\SampleData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * The back office dashboard, screen 09.
 *
 * WHAT IS REAL HERE AND WHAT IS NOT.
 *
 * Every figure on this page is counted from the database at request time.
 * Nothing is stubbed, sampled or carried in a fixture.
 *
 * Screen 09 also draws a visitors chart, a popular-pages list, a popular
 * search terms list and a submission activity panel. There is no analytics
 * collection on this platform and the submission workflow is not built, so
 * those four have no data source. They render as empty states that say
 * exactly what would fill them. A dashboard that invents its numbers is
 * worse than one with a gap in it: the Secretariat would have no way to
 * tell which of the other figures were real.
 *
 * The stat row is six cards in the screen. Two of the screen's six, events
 * and projects, have no model in this build, so the row shows the six real
 * counts instead. The shape is unchanged. Logged in docs/OPEN_ITEMS.md.
 */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('enter-back-office');

        return view('pages.admin.dashboard', [
            'stats' => $this->stats(),
            'byYear' => $this->publicationsByYear(),
            'byType' => $this->publicationsByType(),
            'activity' => $this->recentActivity(),
            'publications' => $this->publicationPage($request),
            'today' => now(),
            // The Secretariat's only signal that the platform is not yet
            // clean, now that the public notices are gone.
            'sampleCounts' => SampleData::counts(),
        ]);
    }

    /**
     * @return list<array{n: int, label: string, note: string}>
     */
    private function stats(): array
    {
        $publications = Publication::count();
        $ict = Publication::whereRelation('sector', 'slug', '!=', 'postal')->count();

        return [
            [
                'n' => $publications,
                'label' => __('admin.stat_publications'),
                'note' => __('admin.stat_publications_note', ['ict' => $ict, 'postal' => $publications - $ict]),
            ],
            [
                'n' => NewsItem::count(),
                'label' => __('admin.stat_news'),
                'note' => __('admin.stat_news_note', ['n' => NewsItem::whereYear('published_at', now()->year)->count()]),
            ],
            [
                'n' => Committee::count(),
                'label' => __('admin.stat_committees'),
                'note' => __('admin.stat_committees_note'),
            ],
            [
                'n' => Country::count(),
                'label' => __('admin.stat_members'),
                'note' => __('admin.stat_members_note', ['n' => Country::has('authorities')->count()]),
            ],
            [
                'n' => Instrument::count(),
                'label' => __('admin.stat_instruments'),
                'note' => __('admin.stat_instruments_note', ['n' => Instrument::where('status', 'in_force')->count()]),
            ],
            [
                'n' => Indicator::count(),
                'label' => __('admin.stat_indicators'),
                'note' => __('admin.stat_indicators_note', ['n' => Indicator::has('values')->count()]),
            ],
        ];
    }

    /**
     * Publications by year of adoption, split ICT and postal.
     *
     * The year is derived in PHP rather than with YEAR(). MariaDB has it and
     * SQLite does not, and the test suite runs on SQLite: a query that works
     * in production and 500s under test is a bug that only shows up once.
     *
     * @return array{rows: list<array{year: int, ict: int, postal: int, total: int}>, max: int, ict: int, postal: int, span: string}
     */
    private function publicationsByYear(): array
    {
        $publications = Publication::query()
            ->whereNotNull('published_on')
            ->with('sector:id,slug')
            ->get(['id', 'sector_id', 'published_on']);

        $years = [];

        foreach ($publications as $publication) {
            $year = (int) $publication->published_on->year;
            $isPostal = $publication->sector?->slug === 'postal';

            $years[$year] ??= ['year' => $year, 'ict' => 0, 'postal' => 0, 'total' => 0];
            $years[$year][$isPostal ? 'postal' : 'ict']++;
            $years[$year]['total']++;
        }

        ksort($years);
        $rows = array_values($years);

        return [
            'rows' => $rows,
            'max' => max(1, max(array_column($rows, 'total') ?: [1])),
            'ict' => array_sum(array_column($rows, 'ict')),
            'postal' => array_sum(array_column($rows, 'postal')),
            'span' => $rows === [] ? '' : $rows[0]['year'].'-'.$rows[count($rows) - 1]['year'],
        ];
    }

    /**
     * @return array{rows: list<array{label: string, ict: int, postal: int, total: int}>, max: int}
     */
    private function publicationsByType(): array
    {
        $publications = Publication::query()
            ->with('sector:id,slug')
            ->get(['id', 'sector_id', 'publication_type']);

        $types = [];

        foreach ($publications as $publication) {
            $label = __($publication->publication_type->labelKey());
            $isPostal = $publication->sector?->slug === 'postal';

            $types[$label] ??= ['label' => $label, 'ict' => 0, 'postal' => 0, 'total' => 0];
            $types[$label][$isPostal ? 'postal' : 'ict']++;
            $types[$label]['total']++;
        }

        uasort($types, fn (array $a, array $b) => $b['total'] <=> $a['total']);
        $rows = array_values($types);

        return [
            'rows' => $rows,
            'max' => max(1, max(array_column($rows, 'total') ?: [1])),
        ];
    }

    /**
     * The audit log is the one activity feed on this platform that is real.
     * It is immutable and it records who as well as what, which is exactly
     * what the screen's panel asks for.
     */
    private function recentActivity(): Collection
    {
        return AuditLog::query()
            ->with('user:id,name')
            ->latest('created_at')
            ->limit(8)
            ->get();
    }

    /**
     * Screen 09: "The repeating resource table. Every content type in the
     * back office uses this pattern." This is that table, with publications
     * in it, and the Blade component it renders is the one every other
     * section reuses.
     */
    private function publicationPage(Request $request): LengthAwarePaginator
    {
        $publications = Publication::query()
            ->with(['sector', 'translations'])
            ->latest('published_on')
            ->paginate(10)
            ->withQueryString();

        // Which languages each publication actually carries. Loaded with the
        // page rather than per row.
        $publications->getCollection()->each(function (Publication $publication): void {
            $locales = $publication->translations
                ->where('field', 'title')
                ->pluck('locale')
                ->intersect(Locales::SUPPORTED)
                ->values();

            $publication->setAttribute('language_codes', $locales->map(
                fn (string $locale) => mb_strtoupper(Locales::short($locale))
            )->all());
        });

        return $publications;
    }
}

@extends('layouts.admin')

{{--
    Screen 09's dashboard, in the new shell.

    THE CONTENT IS THE SCREEN'S. The figures, the two charts, the recent
    activity and the sample-data count are all the ones screen 09 draws.
    What changed on 10 September 2026 is the order and the shell: alerts
    first, then four tiles, then the work waiting on somebody, then the
    charts. A reader who opens this every morning wants to know what needs
    them before they want a histogram of publications by year.

    The welcome panel used to be the first thing on the page and the height
    of the viewport. It is a card at the foot now, and dismissable.
--}}

@section('title', __('admin.section_dashboard'))
@section('note', __('admin.dashboard_note', ['date' => $today->translatedFormat('l j F Y')]))

@section('actions')
    <a class="a-btn a-btn--secondary" href="{{ route('admin.audit', ['locale' => app()->getLocale()]) }}">{{ __('admin.open_audit') }}</a>
    @if (Route::has('admin.pages'))
        {{-- Labelled. The primary action on this page used to be a green
             button with no text in it. --}}
        <a class="a-btn a-btn--primary" href="{{ route('admin.pages', ['locale' => app()->getLocale()]) }}">{{ __('admin.open_content') }}</a>
    @endif
@endsection

@section('content')

    {{-- 1. ALERTS, only when there is one.

         The mail warning is in the layout and is never dismissable: mail
         that goes nowhere is the one misconfiguration that produces no
         error anywhere. These two are informational and go for the
         session once read. --}}
    @if ($sampleCounts !== [] && ! session('dismissed.samples'))
        <div class="a-alert a-alert--pending" role="status" data-dismissable="samples">
            {{-- A real form, so dismissing works with no script at
                 all. Every control on this platform does. --}}
            <form class="a-alert__dismissform" method="POST" action="{{ route('admin.dismiss', ['locale' => app()->getLocale()]) }}">
                @csrf
                <input type="hidden" name="notice" value="samples">
                <button type="submit" class="a-alert__dismiss" aria-label="{{ __('admin.dismiss') }}">
                    <x-shell-icon name="close" :size="16" />
                </button>
            </form>
            <strong>{{ trans_choice('admin.sample_present', $sampleTotal, ['count' => number_format($sampleTotal)]) }}</strong>
            {{ __('admin.sample_body') }}
            <a href="{{ route('admin.samples', ['locale' => app()->getLocale()]) }}">{{ __('admin.nav_samples') }}</a>
        </div>
    @endif

    @if ($awaitingReview->isNotEmpty() && ! session('dismissed.review'))
        <div class="a-alert a-alert--pending" role="status" data-dismissable="review">
            {{-- A real form, so dismissing works with no script at
                 all. Every control on this platform does. --}}
            <form class="a-alert__dismissform" method="POST" action="{{ route('admin.dismiss', ['locale' => app()->getLocale()]) }}">
                @csrf
                <input type="hidden" name="notice" value="review">
                <button type="submit" class="a-alert__dismiss" aria-label="{{ __('admin.dismiss') }}">
                    <x-shell-icon name="close" :size="16" />
                </button>
            </form>
            <strong>{{ __('admin.review_flag_title') }}</strong>
            {{ __('admin.review_pending_body') }}
            @foreach ($awaitingReview as $page)
                <a href="{{ route('admin.pages.edit', ['locale' => app()->getLocale(), 'page' => $page->slug]) }}">{{ $page->translate('title')?->value ?? $page->slug }}</a>@if (! $loop->last), @endif
            @endforeach
        </div>
    @endif

    {{-- 2. FOUR TILES. Every figure is a link to what it counts. --}}
    <div class="a-tiles">
        @foreach ($tiles as $tile)
            <a class="a-tile" href="{{ $tile['url'] }}">
                <span class="a-tile__icon" aria-hidden="true"><x-shell-icon :name="$tile['icon']" :size="20" /></span>
                <span class="a-tile__text">
                    <span class="a-tile__n">{{ number_format($tile['n']) }}</span>
                    <span class="a-tile__label">{{ $tile['label'] }}</span>
                    <span class="a-tile__note">{{ $tile['note'] }}</span>
                </span>
            </a>
        @endforeach
    </div>

    {{-- 3. TWO COLUMNS, 2:1. What happened, and what is waiting. --}}
    <div class="a-split">
        <section class="a-card">
            <div class="a-card__head">
                <h2>{{ __('admin.dash_activity') }}</h2>
                <a class="a-card__meta" href="{{ route('admin.audit', ['locale' => app()->getLocale()]) }}">{{ __('admin.open_audit') }}</a>
            </div>

            @if ($activity->isEmpty())
                <div class="a-empty">
                    <div class="a-empty__title">{{ __('admin.activity_empty_title') }}</div>
                    <div class="a-empty__body">{{ __('admin.activity_empty_body') }}</div>
                </div>
            @else
                <div class="a-rows">
                    @foreach ($activity as $entry)
                        <div class="a-row">
                            <span class="a-row__time">{{ $entry->created_at->diffForHumans() }}</span>
                            <span class="a-row__grow">{{ $entry->describe() }}</span>
                            <span class="a-row__who">{{ $entry->user?->name ?? __('admin.system') }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <div class="a-stack">
            @can(\App\Enums\Permission::ValidateSubmission->value)
                <section class="a-card">
                    <div class="a-card__head">
                        <h2>{{ __('admin.dash_awaiting') }}</h2>
                        <a class="a-card__meta" href="{{ route('admin.submissions', ['locale' => app()->getLocale()]) }}">{{ __('admin.nav_submissions') }}</a>
                    </div>

                    @if ($awaitingSubmissions->isEmpty())
                        <div class="a-empty">
                            <div class="a-empty__body">{{ __('admin.dash_awaiting_empty') }}</div>
                        </div>
                    @else
                        <ul class="a-results" role="list">
                            @foreach ($awaitingSubmissions as $submission)
                                <li>
                                    <a href="{{ route('admin.submissions.show', ['locale' => app()->getLocale(), 'submission' => $submission->id]) }}">{{ $submission->country?->name }}</a>
                                    <span class="a-results__meta">{{ $submission->period }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endcan

            <section class="a-card">
                <div class="a-card__head">
                    <h2>{{ __('admin.dash_drafts') }}</h2>
                    <a class="a-card__meta" href="{{ route('admin.pages', ['locale' => app()->getLocale(), 'status' => 'draft']) }}">{{ __('admin.nav_pages') }}</a>
                </div>

                @if ($draftPages->isEmpty())
                    <div class="a-empty">
                        <div class="a-empty__body">{{ __('admin.dash_drafts_empty') }}</div>
                    </div>
                @else
                    <ul class="a-results" role="list">
                        @foreach ($draftPages as $page)
                            <li>
                                <a href="{{ route('admin.pages.edit', ['locale' => app()->getLocale(), 'page' => $page->slug]) }}">{{ $page->translate('title')?->value ?? $page->slug }}</a>
                                <span class="a-results__meta">{{ $page->updated_at?->isoFormat('D MMM YYYY') }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>
    {{-- Screen 09: repeat(auto-fit,minmax(min(100%,440px),1fr)), gap var(--s-5) --}}
    <div class="a-grid a-grid--wide">
        <div class="a-panel">
            <div class="a-panel__head">
                <h2>{{ __('admin.by_year') }}</h2>
                <span class="a-panel__meta">{{ __('admin.by_year_meta', ['n' => $byYear['ict'] + $byYear['postal'], 'span' => $byYear['span']]) }}</span>
            </div>

            @if ($byYear['rows'] === [])
                <div class="a-empty">
                    <div class="a-empty__title">{{ __('admin.by_year_empty_title') }}</div>
                    <div class="a-empty__body">{{ __('admin.by_year_empty_body') }}</div>
                </div>
            @else
                {{-- Every bar states its count, so the chart reads without
                     colour. The screen says so in its own legend and it is
                     also what SC 1.4.1 requires. --}}
                <div class="a-bars">
                    @foreach ($byYear['rows'] as $year)
                        <div class="a-bars__col">
                            <span class="a-bars__n">{{ $year['total'] }}</span>
                            <span class="a-bars__bar" style="height:{{ round($year['total'] / $byYear['max'] * 100) }}%">
                                @if ($year['postal'] > 0)
                                    <span class="a-bars__seg" data-sector="postal" style="height:{{ round($year['postal'] / $year['total'] * 100) }}%"></span>
                                @endif
                                @if ($year['ict'] > 0)
                                    <span class="a-bars__seg" data-sector="ict" style="height:{{ round($year['ict'] / $year['total'] * 100) }}%"></span>
                                @endif
                            </span>
                            <span class="a-bars__year">{{ $year['year'] }}</span>
                        </div>
                    @endforeach
                </div>

                {{-- The table equivalent. A chart on this platform is never
                     the only way to read the figure. --}}
                <details style="margin-top:var(--s-4)">
                    <summary style="font-size:13.5px;font-weight:600;cursor:pointer">{{ __('admin.as_table') }}</summary>
                    <table class="a-table" style="min-width:0;margin-top:var(--s-3)">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('admin.col_year') }}</th>
                                <th scope="col" data-numeric>{{ __('admin.sector_ict') }}</th>
                                <th scope="col" data-numeric>{{ __('admin.sector_postal') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($byYear['rows'] as $year)
                                <tr>
                                    <th scope="row">{{ $year['year'] }}</th>
                                    <td data-numeric>{{ $year['ict'] }}</td>
                                    <td data-numeric>{{ $year['postal'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </details>

                <div class="a-panel__foot a-legend">
                    <span><span class="a-legend__swatch" style="background:var(--c-green-dark)"></span>{{ __('admin.sector_ict') }}, {{ $byYear['ict'] }}</span>
                    <span><span class="a-legend__swatch" style="background:var(--c-navy)"></span>{{ __('admin.sector_postal') }}, {{ $byYear['postal'] }}</span>
                    <span class="a-legend__note">{{ __('admin.bars_read_without_colour') }}</span>
                </div>
            @endif
        </div>

        <div class="a-panel">
            <div class="a-panel__head">
                <h2>{{ __('admin.by_type') }}</h2>
            </div>

            @if ($byType['rows'] === [])
                <div class="a-empty">
                    <div class="a-empty__title">{{ __('admin.by_year_empty_title') }}</div>
                    <div class="a-empty__body">{{ __('admin.by_year_empty_body') }}</div>
                </div>
            @else
                <div class="a-stack">
                    @foreach ($byType['rows'] as $type)
                        <div>
                            <div class="a-track__label">
                                <b>{{ $type['label'] }}</b>
                                <span>{{ $type['total'] }}</span>
                            </div>
                            <div class="a-track">
                                @if ($type['ict'] > 0)
                                    <span class="a-track__seg" data-sector="ict" style="width:{{ round($type['ict'] / $byType['max'] * 100) }}%"></span>
                                @endif
                                @if ($type['postal'] > 0)
                                    <span class="a-track__seg" data-sector="postal" style="width:{{ round($type['postal'] / $byType['max'] * 100) }}%"></span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Screen 09: repeat(auto-fit,minmax(min(100%,320px),1fr)), gap var(--s-5).
         Three panels in the screen: visitors, popular pages and search
         terms, and submission activity. None of the three has a data
         source in this build, and each says which one it needs. --}}
    <div class="a-grid a-grid--medium">
        <x-admin.empty-panel
            :heading="__('admin.visitors')"
            :title="__('admin.visitors_empty_title')"
            :body="__('admin.visitors_empty_body')" />

        <x-admin.empty-panel
            :heading="__('admin.popular')"
            :title="__('admin.popular_empty_title')"
            :body="__('admin.popular_empty_body')" />

        <x-admin.empty-panel
            :heading="__('admin.submissions')"
            :title="__('admin.submissions_empty_title')"
            :body="__('admin.submissions_empty_body')" />
    </div>

    <x-admin.resource-table
        :title="__('admin.stat_publications')"
        :note="__('admin.publications_note')"
        :columns="[
            ['key' => 'title', 'label' => __('admin.col_title')],
            ['key' => 'type', 'label' => __('admin.col_type')],
            ['key' => 'sector', 'label' => __('admin.col_sector')],
            ['key' => 'year', 'label' => __('admin.col_year'), 'numeric' => true],
            ['key' => 'languages', 'label' => __('admin.col_languages'), 'quiet' => true],
            ['key' => 'status', 'label' => __('admin.col_status')],
            ['key' => 'updated', 'label' => __('admin.col_updated'), 'quiet' => true],
        ]"
        :rows="$publications"
        :row="fn ($p) => [
            'url' => route('publications.show', ['locale' => app()->getLocale(), 'slug' => $p->slug]),
            'cells' => [
                'title' => $p->translate('title')?->value ?? $p->slug,
                'type' => __($p->publication_type->labelKey()),
                'sector' => $p->sector?->name ?? '',
                'year' => $p->published_on?->year ?? '',
                'languages' => implode(' ', $p->language_codes),
                'status' => new \Illuminate\Support\HtmlString(
                    '<span class=\'a-pill\' data-status=\'' . ($p->is_public ? 'published' : 'draft') . '\'>'
                    . e($p->is_public ? __('admin.status_published') : __('admin.status_draft')) . '</span>'
                ),
                'updated' => $p->updated_at?->isoFormat('D MMM YYYY') ?? '',
            ],
            'actions' => [
                ['label' => __('admin.view'), 'url' => route('publications.show', ['locale' => app()->getLocale(), 'slug' => $p->slug])],
            ],
        ]" />

    {{-- 4. THE WELCOME, at the foot and dismissable. It used to be the
         first thing on the page and the height of the viewport.
         Dismissal is per account, which is the mechanism that was
         already there and the right one: somebody who has taken the
         tour has taken it. --}}
    @include('partials.admin-intro')
@endsection
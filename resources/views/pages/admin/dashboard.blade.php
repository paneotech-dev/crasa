@extends('layouts.admin')

@section('title', __('admin.section_dashboard'))
@section('note', __('admin.dashboard_note', ['date' => $today->translatedFormat('l j F Y')]))

@section('actions')
    <a class="a-btn a-btn--secondary" href="{{ route('admin.audit', ['locale' => app()->getLocale()]) }}">{{ __('admin.open_audit') }}</a>
    @if (Route::has('admin.pages'))
        <a class="a-btn a-btn--primary" href="{{ route('admin.pages', ['locale' => app()->getLocale()]) }}">{{ __('admin.open_content') }}</a>
    @endif
@endsection

@section('content')

    @include('partials.admin-intro')

    {{-- Screen 09: repeat(auto-fit,minmax(min(100%,236px),1fr)), gap var(--s-4) --}}
    <div class="a-grid a-grid--stats">
        @foreach ($stats as $stat)
            <div class="a-panel a-panel--tight">
                <div class="a-stat__n">{{ number_format($stat['n']) }}</div>
                <div class="a-stat__label">{{ $stat['label'] }}</div>
                <div class="a-stat__note">{{ $stat['note'] }}</div>
            </div>
        @endforeach
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

    <div class="a-panel">
        <div class="a-panel__head">
            <h2>{{ __('admin.recent_activity') }}</h2>
            <a class="a-panel__meta" href="{{ route('admin.audit', ['locale' => app()->getLocale()]) }}">{{ __('admin.open_audit') }}</a>
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
@endsection

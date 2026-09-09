@extends('layouts.admin')

@section('title', __('admin.media_title'))
@section('note', __('admin.media_note'))

@section('actions')
    {{-- Screen 14 draws the layout toggle as two buttons inside a single
         1.5px navy rule, and Upload as the primary action beside it. --}}
    <div class="a-toggle" role="group" aria-label="{{ __('admin.layout') }}">
        <a href="{{ request()->fullUrlWithQuery(['view' => 'grid']) }}" aria-current="{{ $view === 'grid' ? 'true' : 'false' }}">{{ __('admin.view_grid') }}</a>
        <a href="{{ request()->fullUrlWithQuery(['view' => 'list']) }}" aria-current="{{ $view === 'list' ? 'true' : 'false' }}">{{ __('admin.view_list') }}</a>
    </div>
    <a class="a-btn a-btn--primary" href="{{ route('admin.media.create', ['locale' => app()->getLocale()]) }}">{{ __('admin.upload') }}</a>
@endsection

@section('content')

    @if ($missingAltCount > 0)
        <div class="a-band">
            <strong>{{ trans_choice('admin.media_missing_alt_band', $missingAltCount, ['count' => $missingAltCount]) }}</strong>
            {{ __('admin.media_missing_alt_body') }}
        </div>
    @endif

    <section class="a-resource">
        <div class="a-resource__head">
            <form class="a-filters" method="GET" action="{{ url()->current() }}">
                <input type="hidden" name="view" value="{{ $view }}">
                <label class="a-search">
                    <span class="c-sr">{{ __('admin.media_search') }}</span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M16.5 16.5L21 21"/></svg>
                    <input class="a-search__field" type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('admin.media_search') }}">
                </label>

                <label class="c-sr" for="type">{{ __('admin.col_type') }}</label>
                <select class="a-filter" id="type" name="type">
                    <option value="">{{ __('admin.type_all') }}</option>
                    @foreach ($types as $type)
                        <option value="{{ $type }}" @selected(request('type') === $type)>{{ $type }}</option>
                    @endforeach
                </select>

                <label class="a-filter" for="missing_alt">
                    <input type="checkbox" id="missing_alt" name="missing_alt" value="1" @checked(request()->boolean('missing_alt'))>
                    {{ __('admin.media_missing_alt_filter', ['n' => $missingAltCount]) }}
                </label>

                <button type="submit" class="a-btn a-btn--secondary">{{ __('admin.apply') }}</button>
                <span style="margin-left:auto" class="a-resource__note">{{ trans_choice('admin.media_count', $assets->total(), ['count' => $assets->total()]) }}</span>
            </form>
        </div>

        @if ($assets->isEmpty())
            <div style="padding:var(--s-5)">
                <div class="a-empty">
                    <div class="a-empty__title">{{ __('admin.media_empty_title') }}</div>
                    <div class="a-empty__body">{{ __('admin.media_empty_body') }}</div>
                </div>
            </div>

        @elseif ($view === 'grid')
            <div class="a-media">
                @foreach ($assets as $asset)
                    @php $altCount = count($asset->localesWithAlt()); @endphp
                    <a class="a-asset"
                       data-alt="{{ $altCount === 0 ? 'missing' : ($asset->hasCompleteAlt() ? 'complete' : 'partial') }}"
                       href="{{ route('admin.media.edit', ['locale' => app()->getLocale(), 'asset' => $asset->id]) }}">
                        <span class="a-asset__thumb">
                            @if ($asset->url(400))
                                {{-- The thumbnail is a rendition. Nothing on
                                     this platform points at an original. --}}
                                <img src="{{ $asset->url(400) }}" alt="" loading="lazy" width="{{ $asset->width }}" height="{{ $asset->height }}">
                            @endif
                        </span>
                        <span class="a-asset__body">
                            <span class="a-asset__name">{{ $asset->original_name }}</span>
                            <span class="a-asset__meta">{{ $asset->humanSize() }}{{ $asset->dimensions() ? ', '.$asset->dimensions() : '' }}</span>
                            <span class="a-asset__usage" @if ($usage[$asset->id] === []) data-unused @endif>
                                {{ $usage[$asset->id] === [] ? __('admin.media_unused') : trans_choice('admin.media_used_on', count($usage[$asset->id]), ['count' => count($usage[$asset->id])]) }}
                            </span>
                            @if ($altCount < 3)
                                <span class="a-asset__warning">{{ __('admin.media_alt_missing_short') }}</span>
                            @endif
                        </span>
                    </a>
                @endforeach
            </div>

        @else
            <div class="a-tablewrap">
                <table class="a-table" style="min-width:820px">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('admin.col_asset') }}</th>
                            <th scope="col">{{ __('admin.col_dimensions') }}</th>
                            <th scope="col">{{ __('admin.col_alt') }}</th>
                            <th scope="col">{{ __('admin.col_used_on') }}</th>
                            <th scope="col">{{ __('admin.col_uploaded') }}</th>
                            <th scope="col">{{ __('admin.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($assets as $asset)
                            @php
                                $altCount = count($asset->localesWithAlt());
                                $state = $altCount === 0 ? 'missing' : ($altCount === 3 ? 'complete' : 'partial');
                            @endphp
                            <tr>
                                <th scope="row">
                                    <a href="{{ route('admin.media.edit', ['locale' => app()->getLocale(), 'asset' => $asset->id]) }}">{{ $asset->original_name }}</a>
                                </th>
                                <td data-quiet>{{ $asset->dimensions() ?? '—' }}</td>
                                <td><span class="a-altstate" data-state="{{ $state }}">{{ __('admin.alt_state_'.$state, ['n' => $altCount]) }}</span></td>
                                <td>{{ $usage[$asset->id] === [] ? __('admin.media_unused') : trans_choice('admin.media_used_on', count($usage[$asset->id]), ['count' => count($usage[$asset->id])]) }}</td>
                                <td data-quiet>{{ $asset->created_at->isoFormat('D MMM YYYY') }}</td>
                                <td>
                                    <span class="a-table__actions">
                                        <a href="{{ route('admin.media.edit', ['locale' => app()->getLocale(), 'asset' => $asset->id]) }}">{{ __('admin.open') }}</a>
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($assets->hasPages())
            <div class="a-pager">{{ $assets->links() }}</div>
        @endif
    </section>
@endsection

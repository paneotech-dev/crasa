@extends('layouts.extranet')

@section('title', __('extranet.folders'))
@section('note', __('extranet.note'))

@section('content')
    {{-- Screen 12 states the boundary on screen rather than leaving it to
         be inferred from an empty list. A member state that sees three
         folders should know it is seeing all of its own, not a subset of
         everyone's. --}}
    @if ($country)
        <section class="e-boundary">
            <p class="e-boundary__kicker">{{ __('extranet.boundary_title') }}</p>
            <p><strong>{{ __('extranet.boundary_inside', ['country' => $country->name]) }}</strong> {{ __('extranet.boundary_body') }}</p>
        </section>
    @endif

    <div class="a-split">
        <section class="a-resource a-split__main" style="flex:999 1 460px">
            <div class="a-resource__head">
                <h2 class="a-resource__title">{{ __('extranet.folders') }}</h2>
                <div class="a-resource__note">{{ __('extranet.folders_note') }}</div>
            </div>

            @if ($roots->isEmpty())
                <div style="padding:var(--s-5)">
                    <div class="a-empty">
                        <div class="a-empty__title">{{ __('extranet.empty_title') }}</div>
                        <div class="a-empty__body">{{ __('extranet.empty_body') }}</div>
                    </div>
                </div>
            @else
                <div class="e-tree">
                    @foreach ($roots as $folder)
                        <a class="e-tree__row" href="{{ route('extranet.folder', ['locale' => app()->getLocale(), 'folder' => $folder->id]) }}">
                            <span class="e-tree__icon" aria-hidden="true">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M3 7a1 1 0 011-1h5l2 2h9a1 1 0 011 1v9a1 1 0 01-1 1H4a1 1 0 01-1-1z"/></svg>
                            </span>
                            <span class="e-tree__label">{{ $folder->name }}</span>
                            <span class="e-tree__meta">
                                <span class="a-pill" data-status="{{ $folder->clearance->value }}">{{ $folder->clearance->label() }}</span>
                                {{ trans_choice('extranet.child_count', $folder->children->count(), ['count' => $folder->children->count()]) }}
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        <aside class="a-split__side" style="flex:1 1 300px;max-width:400px">
            @if ($committees->isNotEmpty())
                <div class="a-panel">
                    <div class="a-panel__head"><h2>{{ __('extranet.your_committees') }}</h2></div>
                    <div class="a-rows">
                        @foreach ($committees as $committee)
                            <div class="a-row">
                                <span class="a-row__grow">
                                    <a href="{{ route('extranet.committee', ['locale' => app()->getLocale(), 'committee' => $committee->slug]) }}">{{ $committee->name }}</a>
                                    <span class="a-crow__where" style="display:block">{{ __('committees.role_'.$committee->pivot->role) }}</span>
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="a-panel">
                <div class="a-panel__head">
                    <h2>{{ __('extranet.upcoming') }}</h2>
                    <a class="a-panel__meta" href="{{ route('extranet.calendar', ['locale' => app()->getLocale()]) }}">{{ __('extranet.open_calendar') }}</a>
                </div>
                @if ($upcoming->isEmpty())
                    <div class="a-empty">
                        <div class="a-empty__title">{{ __('extranet.no_meetings_title') }}</div>
                        <div class="a-empty__body">{{ __('extranet.no_meetings_body') }}</div>
                    </div>
                @else
                    <div class="a-rows">
                        @foreach ($upcoming as $meeting)
                            <div class="a-row">
                                <span class="a-row__time">{{ $meeting->starts_on->isoFormat('D MMM') }}</span>
                                <span class="a-row__grow">
                                    {{ $meeting->title }}
                                    @if ($meeting->location)<span class="a-crow__where" style="display:block">{{ $meeting->location }}</span>@endif
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </aside>
    </div>
@endsection

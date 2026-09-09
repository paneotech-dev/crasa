@extends('layouts.extranet')

@section('title', $committee->name)
@section('note', __('extranet.workspace_note'))

@section('crumbs')
    <li><a href="{{ route('extranet.folders', ['locale' => app()->getLocale()]) }}">{{ __('extranet.title') }}</a></li>
    <li aria-current="page">{{ $committee->name }}</li>
@endsection

@section('content')
    <div class="a-split">
        <div class="a-split__main" style="flex:999 1 460px;display:flex;flex-direction:column;gap:var(--s-5)">
            <section class="a-panel">
                <div class="a-panel__head">
                    <h2>{{ __('extranet.mandate') }}</h2>
                    @if ($committee->sector)
                        <span class="a-panel__meta">{{ $committee->sector->name }}</span>
                    @endif
                </div>
                @if ($committee->translate('mandate'))
                    <p class="a-crow__value">{{ $committee->translated('mandate') }}</p>
                @else
                    <div class="a-empty">
                        <div class="a-empty__title">{{ __('extranet.mandate_empty_title') }}</div>
                        <div class="a-empty__body">{{ __('extranet.mandate_empty_body') }}</div>
                    </div>
                @endif
            </section>

            <section class="a-panel">
                <div class="a-panel__head"><h2>{{ __('extranet.workspace_documents') }}</h2></div>
                @if ($folders->isEmpty())
                    <div class="a-empty">
                        <div class="a-empty__title">{{ __('extranet.no_workspace_folders_title') }}</div>
                        <div class="a-empty__body">{{ __('extranet.no_workspace_folders_body') }}</div>
                    </div>
                @else
                    <div class="e-tree">
                        @foreach ($folders as $folder)
                            <a class="e-tree__row" href="{{ route('extranet.folder', ['locale' => app()->getLocale(), 'folder' => $folder->id]) }}">
                                <span class="e-tree__icon" aria-hidden="true">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M3 7a1 1 0 011-1h5l2 2h9a1 1 0 011 1v9a1 1 0 01-1 1H4a1 1 0 01-1-1z"/></svg>
                                </span>
                                <span class="e-tree__label">{{ $folder->name }}</span>
                                <span class="e-tree__meta"><span class="a-pill" data-status="{{ $folder->clearance->value }}">{{ $folder->clearance->label() }}</span></span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="a-panel">
                <div class="a-panel__head">
                    <h2>{{ __('extranet.meetings') }}</h2>
                </div>
                @if ($meetings->isEmpty())
                    <div class="a-empty">
                        <div class="a-empty__title">{{ __('extranet.no_meetings_title') }}</div>
                        <div class="a-empty__body">{{ __('extranet.no_meetings_body') }}</div>
                    </div>
                @else
                    <div class="a-rows">
                        @foreach ($meetings as $meeting)
                            <div class="a-row">
                                <span class="a-row__time">{{ $meeting->starts_on->isoFormat('D MMM YYYY') }}</span>
                                <span class="a-row__grow">
                                    {{ $meeting->title }}
                                    @if ($meeting->location)<span class="a-crow__where" style="display:block">{{ $meeting->location }}</span>@endif
                                </span>
                                <span class="a-row__who">{{ __('extranet.kind_'.$meeting->kind) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>

        <aside class="a-split__side" style="flex:1 1 300px;max-width:400px">
            <div class="a-panel">
                <div class="a-panel__head"><h2>{{ __('extranet.seats') }}</h2></div>
                <div class="a-rows">
                    @foreach ($members as $member)
                        <div class="a-row">
                            <span class="a-identity__initials" aria-hidden="true">{{ strtoupper($member->iso_code) }}</span>
                            <span class="a-row__grow">{{ $member->name }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </aside>
    </div>
@endsection

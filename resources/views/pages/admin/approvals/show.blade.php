@extends('layouts.admin')
@php
    $title = \App\Support\Approvals::title($item);
    $type = $item ? \App\Support\Approvals::typeOf($item) : null;
    $locale = app()->getLocale();
    $st = ['in_review' => 'in_review', 'approved' => 'published', 'returned' => 'returned', 'withdrawn' => 'draft'][$approval->state] ?? 'draft';
@endphp
@section('title', __('fa.show_title', ['title' => $title]))
@section('note', __('fa.show_sent', ['name' => $approval->submitter?->name ?? __('admin.no_actor'), 'date' => $approval->submitted_at?->displayed()->isoFormat('D MMMM YYYY, HH:mm')]))

@section('crumbs')
    <li><a href="{{ route('admin.approvals', ['locale' => $locale]) }}">{{ __('fa.inbox_title') }}</a></li>
    <li aria-current="page">{{ $title }}</li>
@endsection

@section('content')
    <div class="aw-split">
        <div class="aw-split__main">
            <section class="a-panel" aria-labelledby="aw-preview">
                <div class="a-panel__head">
                    <h2 id="aw-preview">{{ __('fa.show_preview') }}</h2>
                    @include('pages.admin.approvals._state', ['state' => $st])
                </div>
                @if ($type)<p class="a-hint">{{ __('fa.type_'.$type) }}</p>@endif
                @foreach (\App\Support\Locales::SUPPORTED as $lang)
                    @php $t = $approval->snapshot['translations']['title.'.$lang] ?? ($lang === 'en' ? ($approval->snapshot['fields']['title'] ?? null) : null); @endphp
                    @if ($t)
                        <p class="aw-lang"><span class="aw-lang__code">{{ strtoupper($lang) }}</span> {{ $t }}</p>
                    @endif
                @endforeach
                @php $body = $approval->snapshot['translations']['body.'.$locale] ?? $approval->snapshot['translations']['summary.'.$locale] ?? $approval->snapshot['translations']['body.en'] ?? $approval->snapshot['translations']['summary.en'] ?? null; @endphp
                @if ($body)
                    <div class="aw-body">{!! nl2br(e(\Illuminate\Support\Str::limit(strip_tags($body), 1500))) !!}</div>
                @endif
                <p>
                    @if ($item instanceof \App\Models\Page)
                        <a href="{{ route('admin.pages.preview', ['locale' => $locale, 'page' => $item->slug]) }}" target="_blank" rel="noopener">{{ __('fa.show_preview_page') }}</a>
                    @endif
                    @if ($item)
                        <a class="aw-gap" href="{{ \App\Support\Approvals::adminUrl($item) }}">{{ __('fa.show_edit_item') }}</a>
                    @endif
                </p>
            </section>

            @if ($approval->note)
                <section class="a-panel" aria-labelledby="aw-note-h">
                    <div class="a-panel__head"><h2 id="aw-note-h">{{ __('fa.show_note') }}</h2></div>
                    <blockquote class="aw-quote">{{ $approval->note }}</blockquote>
                </section>
            @endif

            <section class="a-panel" aria-labelledby="aw-changes">
                <div class="a-panel__head"><h2 id="aw-changes">{{ __('fa.show_changes') }}</h2></div>
                @if ($changes === [])
                    <p>{{ __('fa.show_no_changes') }}</p>
                @else
                    @if ($history->where('state', 'approved')->where('id', '<', $approval->id)->isEmpty())
                        <p class="a-hint">{{ __('fa.show_first') }}</p>
                    @endif
                    <div class="a-tablewrap">
                        <table class="a-table aw-table aw-diff">
                            <caption class="c-sr">{{ __('fa.show_changes') }}</caption>
                            <thead><tr>
                                <th scope="col">{{ __('fa.show_field') }}</th>
                                <th scope="col">{{ __('fa.show_before') }}</th>
                                <th scope="col">{{ __('fa.show_after') }}</th>
                            </tr></thead>
                            <tbody>
                            @foreach ($changes as $change)
                                <tr>
                                    <th scope="row">{{ $change['key'] }}</th>
                                    <td>{{ $change['before'] === null || $change['before'] === '' ? __('fa.show_empty') : \Illuminate\Support\Str::limit(strip_tags($change['before']), 300) }}</td>
                                    <td>{{ $change['after'] === null || $change['after'] === '' ? __('fa.show_empty') : \Illuminate\Support\Str::limit(strip_tags($change['after']), 300) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        <aside class="aw-split__side">
            @error('approval')
                <p class="a-notice a-notice--error" role="alert">{{ $message }}</p>
            @enderror

            @if ($mayDecide)
                <section class="a-panel aw-decide" aria-labelledby="aw-approve-h">
                    <div class="a-panel__head"><h2 id="aw-approve-h">{{ __('fa.show_approve') }}</h2></div>
                    <form class="a-form" method="POST" action="{{ route('admin.approvals.approve', ['locale' => $locale, 'approval' => $approval->id]) }}">
                        @csrf @method('PUT')
                        <div class="a-field">
                            <label class="a-label" for="approve-comment">{{ __('fa.show_approve_comment') }}</label>
                            <textarea class="a-input" id="approve-comment" name="comment" rows="2"></textarea>
                        </div>
                        <button type="submit" class="a-btn a-btn--primary" data-approve>{{ __('fa.show_approve') }}<span class="c-sr">: {{ $title }}</span></button>
                    </form>
                </section>
                <section class="a-panel aw-decide" aria-labelledby="aw-return-h">
                    <div class="a-panel__head"><h2 id="aw-return-h">{{ __('fa.show_return') }}</h2></div>
                    <form class="a-form" method="POST" action="{{ route('admin.approvals.return', ['locale' => $locale, 'approval' => $approval->id]) }}">
                        @csrf @method('PUT')
                        <div @class(['a-field', 'aw-field--error' => $errors->has('comment')])>
                            <label class="a-label" for="return-comment">{{ __('fa.show_return_comment') }} <span class="a-required">*</span></label>
                            <textarea class="a-input" id="return-comment" name="comment" rows="4" required aria-describedby="return-help @error('comment') return-error @enderror">{{ old('comment') }}</textarea>
                            <span class="a-hint" id="return-help">{{ __('fa.show_return_help') }}</span>
                            @error('comment')<span class="aw-error" id="return-error">{{ $message }}</span>@enderror
                        </div>
                        <button type="submit" class="a-btn a-btn--secondary" data-return>{{ __('fa.show_return') }}<span class="c-sr">: {{ $title }}</span></button>
                    </form>
                </section>
            @elseif ($approval->isOpen() && $ownItem)
                <p class="a-notice a-notice--pending">{{ __('fa.show_four_eyes') }}</p>
            @elseif (! $approval->isOpen())
                <p class="a-notice">{{ __('fa.show_decided', ['state' => __('fa.state_'.$st), 'name' => $approval->decider?->name ?? '', 'date' => $approval->decided_at?->displayed()->isoFormat('D MMMM YYYY, HH:mm')]) }}</p>
                @if ($approval->comment)<blockquote class="aw-quote">{{ $approval->comment }}</blockquote>@endif
            @endif

            <section class="a-panel" aria-labelledby="aw-history">
                <div class="a-panel__head"><h2 id="aw-history">{{ __('fa.show_history') }}</h2></div>
                <ol class="aw-history">
                    @foreach ($history as $h)
                        <li>
                            <span class="c-num">{{ $h->submitted_at?->displayed()->isoFormat('D MMM, HH:mm') }}</span>
                            {{ $h->submitter?->name }}
                            @include('pages.admin.approvals._state', ['state' => ['in_review' => 'in_review', 'approved' => 'published', 'returned' => 'returned', 'withdrawn' => 'draft'][$h->state] ?? 'draft'])
                            @if ($h->decider) {{ $h->decider->name }} @endif
                        </li>
                    @endforeach
                </ol>
            </section>
        </aside>
    </div>
@endsection

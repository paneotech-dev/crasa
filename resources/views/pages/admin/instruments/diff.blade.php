@extends('layouts.admin')

{{--
    Two versions, side by side, word by word.

    A line diff on regulatory prose marks a whole paragraph changed because
    one date moved in it, and leaves the reader to find the date. "What
    exactly changed in clause 4" is the question a regulator asks.

    NOTHING HERE IS COLOUR ALONE. An addition carries a + and a removal a -,
    and each is announced to a screen reader by name. Screen 05's
    translation editor marks a changed cell with a border and a word rather
    than a fill, for the same reason.
--}}

@section('title', __('admin.diff_title'))
@section('note', $instrument->translated('title', $instrument->slug))

@section('crumbs')
    <li><a href="{{ route('admin.resources', ['locale' => app()->getLocale(), 'resource' => 'instruments']) }}">{{ __('admin.nav_instruments') }}</a></li>
    <li><a href="{{ route('admin.instruments.versions', ['locale' => app()->getLocale(), 'instrument' => $instrument->slug]) }}">{{ __('admin.versions_title') }}</a></li>
    <li aria-current="page">{{ __('admin.diff_title') }}</li>
@endsection

@section('content')
    {{-- Which two, and in which language. A GET form, so a comparison is a
         URL somebody can send to the person who made the change. --}}
    <section class="a-card">
        <form class="a-diffpick" method="GET">
            <div class="a-field">
                <label class="a-label" for="from">{{ __('admin.diff_from') }}</label>
                <select class="a-select" id="from" name="from">
                    @foreach ($versions as $version)
                        <option value="{{ $version->id }}" @selected($version->id === $before->id)>
                            {{ __('admin.page_history_version', ['n' => $version->version_number]) }},
                            {{ $version->created_at?->displayed()->isoFormat('D MMM YYYY') }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="a-field">
                <label class="a-label" for="to">{{ __('admin.diff_to') }}</label>
                <select class="a-select" id="to" name="to">
                    @foreach ($versions as $version)
                        <option value="{{ $version->id }}" @selected($version->id === $after->id)>
                            {{ __('admin.page_history_version', ['n' => $version->version_number]) }},
                            {{ $version->created_at?->displayed()->isoFormat('D MMM YYYY') }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="a-field">
                <label class="a-label" for="lang">{{ __('admin.diff_language') }}</label>
                <select class="a-select" id="lang" name="lang">
                    @foreach ($locales as $code)
                        <option value="{{ $code }}" @selected($code === $locale)>{{ \App\Support\Locales::nameOf($code) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="a-form__actions">
                <button class="a-btn a-btn--secondary" type="submit">{{ __('admin.diff_compare') }}</button>
            </div>
        </form>
    </section>

    {{-- Who made each of the two, and when. A diff that does not say who is
         half a provenance record. --}}
    <div class="a-diffheads">
        @foreach ([['before', $before], ['after', $after]] as [$side, $version])
            <div class="a-diffhead" data-side="{{ $side }}">
                <span class="a-diffhead__which">{{ __('admin.diff_'.$side) }}</span>
                <span class="a-diffhead__n">{{ __('admin.page_history_version', ['n' => $version->version_number]) }}</span>
                <span class="a-diffhead__meta">
                    {{ $version->created_at?->displayed()->isoFormat('D MMMM YYYY, HH:mm') }},
                    {{ $version->author?->name ?? __('admin.system') }}
                </span>
                <span class="a-diffhead__summary">{{ $version->change_summary }}</span>
            </div>
        @endforeach
    </div>

    @foreach ($fields as $field => $diff)
        <section class="a-card">
            <div class="a-card__head">
                <h2>{{ __('admin.field_'.$field) }}</h2>
                <span class="a-card__meta">
                    @if ($diff['truncated'])
                        {{ __('admin.diff_too_long') }}
                    @elseif ($diff['added'] === 0 && $diff['removed'] === 0)
                        {{ __('admin.diff_unchanged') }}
                    @else
                        {{ __('admin.diff_counts', ['added' => $diff['added'], 'removed' => $diff['removed']]) }}
                    @endif
                </span>
            </div>

            @if ($diff['truncated'])
                <p class="a-hint">{{ __('admin.diff_too_long_body') }}</p>
            @elseif ($diff['segments'] === [])
                <p class="a-hint">{{ __('admin.diff_both_empty') }}</p>
            @else
                <p class="a-diff">
                    @foreach ($diff['segments'] as $segment)
                        @if ($segment['type'] === 'kept')
                            {{ $segment['text'] }}
                        @else
                            {{-- The mark is a glyph and a word, never the
                                 fill alone: SC 1.4.1. --}}
                            <{{ $segment['type'] === 'added' ? 'ins' : 'del' }} class="a-diff__{{ $segment['type'] }}">
                                <span class="c-sr">{{ __('admin.diff_'.$segment['type'].'_from') }}</span>
                                <span aria-hidden="true" class="a-diff__sign">{{ $segment['type'] === 'added' ? '+' : '−' }}</span>{{ $segment['text'] }}<span class="c-sr">{{ __('admin.diff_end') }}</span>
                            </{{ $segment['type'] === 'added' ? 'ins' : 'del' }}>
                        @endif
                    @endforeach
                </p>
            @endif
        </section>
    @endforeach
@endsection

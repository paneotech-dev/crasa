@extends('layouts.admin')

{{--
    Writing an announcement.

    English is required and the other two are not. CLAUDE.md: an item may
    publish in English only, because requiring all three would block the
    Secretariat from telling anybody anything until a translator was free.

    The audience is two selects, not a rules engine. Everybody, or one
    sector, or one committee. Those are the three cases asked for, and a
    general targeting system for three cases is one whose behaviour nobody
    can predict.
--}}

@php($isNew = ! $announcement->exists)

@section('title', $isNew ? __('announcements.write') : __('announcements.edit_title'))
@section('note', $isNew ? __('announcements.write_note') : $announcement->translated('title', $announcement->slug))

@section('crumbs')
    <li><a href="{{ route('admin.announcements', ['locale' => app()->getLocale()]) }}">{{ __('announcements.admin_title') }}</a></li>
    <li aria-current="page">{{ $isNew ? __('announcements.write') : __('admin.edit') }}</li>
@endsection

@section('content')
    @if (! $isNew)
        <section class="a-card">
            <div class="a-card__head">
                <h2>{{ __('announcements.status_heading') }}</h2>
                <span class="a-card__meta">
                    <span class="a-pill" data-status="{{ $announcement->status }}">{{ __('announcements.status_'.$announcement->status) }}</span>
                </span>
            </div>

            @if ($announcement->status !== 'published')
                <p class="a-hint">{{ __('announcements.draft_note') }}</p>
            @endif

            @can('publish', $announcement)
                {{-- Publishing is what notifies. A draft edited six times
                     reaches nobody until somebody decides it is ready. --}}
                <div class="a-form__actions">
                    <form method="POST"
                          action="{{ route($announcement->status === 'published' ? 'admin.announcements.unpublish' : 'admin.announcements.publish', ['locale' => app()->getLocale(), 'announcement' => $announcement->slug]) }}">
                        @csrf
                        @method('PUT')
                        <button class="a-btn {{ $announcement->status === 'published' ? 'a-btn--secondary' : 'a-btn--primary' }}" type="submit">
                            {{ $announcement->status === 'published' ? __('announcements.unpublish') : __('announcements.publish') }}
                        </button>
                    </form>
                </div>
            @endcan
        </section>
    @endif

    <form class="a-form a-form--card" method="post"
          action="{{ $isNew
              ? route('admin.announcements.store', ['locale' => app()->getLocale()])
              : route('admin.announcements.update', ['locale' => app()->getLocale(), 'announcement' => $announcement->slug]) }}">
        @csrf
        @unless ($isNew)
            @method('PUT')
        @endunless

        @foreach (\App\Support\Locales::SUPPORTED as $locale)
            <fieldset class="a-fieldset">
                <legend class="a-legend">{{ \App\Support\Locales::nameOf($locale) }}</legend>

                <div class="a-field">
                    <label class="a-label" for="title_{{ $locale }}">
                        {{ __('announcements.field_title') }}
                        @if ($locale === 'en')<span class="a-required">{{ __('admin.required') }}</span>@endif
                    </label>
                    <input class="a-input" type="text" id="title_{{ $locale }}" name="title_{{ $locale }}" maxlength="200"
                           @if ($locale === 'en') required @endif
                           value="{{ old('title_'.$locale, $announcement->translated('title', '', $locale)) }}">
                    @error('title_'.$locale)<p class="a-hint" role="alert">{{ $message }}</p>@enderror
                </div>

                <div class="a-field">
                    <label class="a-label" for="body_{{ $locale }}">
                        {{ __('announcements.field_body') }}
                        @if ($locale === 'en')<span class="a-required">{{ __('admin.required') }}</span>@endif
                    </label>
                    <textarea class="a-input" id="body_{{ $locale }}" name="body_{{ $locale }}" rows="8" maxlength="8000"
                              @if ($locale === 'en') required @endif>{{ old('body_'.$locale, $announcement->translated('body', '', $locale)) }}</textarea>
                    @error('body_'.$locale)<p class="a-hint" role="alert">{{ $message }}</p>@enderror
                </div>
            </fieldset>
        @endforeach

        <fieldset class="a-fieldset">
            <legend class="a-legend">{{ __('announcements.audience') }}</legend>
            <p class="a-hint">{{ __('announcements.audience_hint') }}</p>

            <div class="a-field">
                <label class="a-label" for="sector_id">{{ __('announcements.field_sector') }}</label>
                <select class="a-select" id="sector_id" name="sector_id">
                    <option value="">{{ __('announcements.audience_all') }}</option>
                    @foreach ($sectors as $sector)
                        <option value="{{ $sector->id }}" @selected(old('sector_id', $announcement->sector_id) == $sector->id)>{{ $sector->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="a-field">
                <label class="a-label" for="committee_id">{{ __('announcements.field_committee') }}</label>
                <select class="a-select" id="committee_id" name="committee_id">
                    <option value="">{{ __('announcements.audience_all') }}</option>
                    @foreach ($committees as $committee)
                        <option value="{{ $committee->id }}" @selected(old('committee_id', $announcement->committee_id) == $committee->id)>{{ $committee->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="a-field">
                <label class="a-label" for="expires_on">{{ __('announcements.field_expires') }}</label>
                <input class="a-input" type="date" id="expires_on" name="expires_on"
                       value="{{ old('expires_on', $announcement->expires_on?->toDateString()) }}">
                <p class="a-hint">{{ __('announcements.expires_hint') }}</p>
            </div>
        </fieldset>

        <div class="a-form__actions">
            <button class="a-btn a-btn--primary" type="submit">{{ __('announcements.save') }}</button>
        </div>
    </form>
@endsection

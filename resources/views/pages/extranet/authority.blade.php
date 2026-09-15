@extends('layouts.extranet')

{{--
    The member state's own record. Extranet item 3.

    This form proposes rather than saves. The authority record is what the
    public members page shows and what a ministry cites, so a
    representative's edit goes to the Secretariat before it goes live. That
    is the same workflow as the confirmation session in Lilongwe.

    The form shows what was last proposed rather than what the platform
    still holds, so a representative coming back sees their own words.
--}}

@section('title', __('authority.title'))
@section('note', $country->name)

@section('content')
    @if ($open)
        <div class="a-band" role="note">
            <strong>{{ __('authority.open_title') }}</strong>
            {{ __('authority.open_body', ['date' => $open->submitted_at?->isoFormat('D MMMM YYYY')]) }}
        </div>
    @elseif ($lastDecision?->status === 'returned')
        {{-- What the Secretariat said, before the form rather than after
             it. A representative who has to hunt for the reason answers the
             wrong thing. --}}
        <div class="a-band a-band--error" role="alert">
            <strong>{{ __('authority.returned_title') }}</strong>
            <p>{{ $lastDecision->review_note }}</p>
        </div>
    @elseif ($authority?->isConfirmed())
        <div class="a-band" role="note">
            {{ __('authority.confirmed_note', [
                'name' => $authority->confirmed_by_name,
                'date' => $authority->confirmed_at?->isoFormat('D MMMM YYYY'),
            ]) }}
        </div>
    @endif

    <form class="a-form a-form--card" method="POST" enctype="multipart/form-data"
          action="{{ route('authority.update', ['locale' => app()->getLocale()]) }}">
        @csrf
        @method('PUT')

        <fieldset class="a-fieldset">
            <legend class="a-legend">{{ __('authority.the_authority') }}</legend>

            <div class="a-field">
                <label class="a-label" for="name">{{ __('authority.field_name') }}<span class="a-required">{{ __('admin.required') }}</span></label>
                <input class="a-input" type="text" id="name" name="name" maxlength="200" required
                       value="{{ old('name', $values['name']) }}">
                @error('name')<p class="a-hint" role="alert">{{ $message }}</p>@enderror
            </div>

            <div class="a-field">
                <label class="a-label" for="acronym">{{ __('authority.field_acronym') }}<span class="a-required">{{ __('admin.required') }}</span></label>
                <input class="a-input" type="text" id="acronym" name="acronym" maxlength="30" required
                       value="{{ old('acronym', $values['acronym']) }}">
                @error('acronym')<p class="a-hint" role="alert">{{ $message }}</p>@enderror
            </div>

            <div class="a-field">
                <label class="a-label" for="website">{{ __('authority.field_website') }}</label>
                <input class="a-input" type="url" id="website" name="website" maxlength="255"
                       value="{{ old('website', $values['website']) }}">
                @error('website')<p class="a-hint" role="alert">{{ $message }}</p>@enderror
            </div>

            <div class="a-field">
                <label class="a-label" for="established_year">{{ __('authority.field_established') }}</label>
                <input class="a-input" type="number" id="established_year" name="established_year"
                       min="1900" max="{{ now()->year }}" value="{{ old('established_year', $values['established_year']) }}">
            </div>

            <div class="a-field">
                <label class="a-label" for="logo">{{ __('authority.field_logo') }}</label>
                @if ($values['logo_path'] ?? null)
                    <img class="a-media" alt="" width="120" src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($values['logo_path']) }}">
                @endif
                <input class="a-input" type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/svg+xml">
                <p class="a-hint">{{ __('authority.logo_hint') }}</p>
                @error('logo')<p class="a-hint" role="alert">{{ $message }}</p>@enderror
            </div>
        </fieldset>

        <fieldset class="a-fieldset">
            <legend class="a-legend">{{ __('authority.mandate') }}</legend>
            <p class="a-hint">{{ __('authority.mandate_hint') }}</p>

            @foreach ($sectors as $sector)
                <div class="a-check">
                    <input type="checkbox" id="sector_{{ $sector->id }}" name="sectors[]" value="{{ $sector->id }}"
                           @checked(in_array($sector->id, old('sectors', $values['sectors'] ?? []), false))>
                    <span class="a-check__text">
                        <label class="a-check__label" for="sector_{{ $sector->id }}">{{ $sector->name }}</label>
                    </span>
                </div>
            @endforeach
        </fieldset>

        <fieldset class="a-fieldset">
            <legend class="a-legend">{{ __('authority.seats') }}</legend>
            <p class="a-hint">{{ __('authority.seats_hint') }}</p>

            @foreach ($committees as $committee)
                <div class="a-check">
                    <input type="checkbox" id="committee_{{ $committee->id }}" name="committees[]" value="{{ $committee->id }}"
                           @checked(in_array($committee->id, old('committees', $values['committees'] ?? []), false))>
                    <span class="a-check__text">
                        <label class="a-check__label" for="committee_{{ $committee->id }}">{{ $committee->name }}</label>
                    </span>
                </div>
            @endforeach
        </fieldset>

        <div class="a-form__actions">
            <button class="a-btn a-btn--primary" type="submit">{{ __('authority.propose') }}</button>
        </div>
    </form>
@endsection

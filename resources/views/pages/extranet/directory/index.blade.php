@extends('layouts.extranet')

{{--
    The members directory. Extranet item 4.

    Two things on one page, because they are the same subject from two
    sides: who is at the fourteen regulators, and who this representative is
    responsible for keeping right.

    Consent gates the listing. A person appears in the directory because
    they said their details may, and they come out of it the moment that is
    withdrawn. These are named individuals' work numbers across fourteen
    jurisdictions with fourteen data protection regimes, so the default for
    a row somebody else typed in is invisible.
--}}

@section('title', __('directory.title'))
@section('note', __('directory.note'))

@section('content')
    <form class="a-filters" method="GET" action="{{ route('directory', ['locale' => app()->getLocale()]) }}">
        <div class="a-field">
            <label class="a-label" for="q">{{ __('directory.search') }}</label>
            <input class="a-input" type="search" id="q" name="q" value="{{ $filters['q'] }}">
        </div>

        <div class="a-field">
            <label class="a-label" for="authority">{{ __('directory.filter_authority') }}</label>
            <select class="a-select" id="authority" name="authority">
                <option value="">{{ __('directory.all_authorities') }}</option>
                @foreach ($authorities as $authority)
                    <option value="{{ $authority->id }}" @selected($filters['authority'] == $authority->id)>{{ $authority->acronym ?: $authority->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="a-field">
            <label class="a-label" for="sector">{{ __('directory.filter_sector') }}</label>
            <select class="a-select" id="sector" name="sector">
                <option value="">{{ __('directory.all_sectors') }}</option>
                @foreach ($sectors as $sector)
                    <option value="{{ $sector->slug }}" @selected($filters['sector'] === $sector->slug)>{{ $sector->name }}</option>
                @endforeach
            </select>
        </div>

        <button class="a-btn a-btn--secondary" type="submit">{{ __('directory.apply') }}</button>
    </form>

    <section class="a-card a-card--flush">
        @if ($contacts->isEmpty())
            <div class="a-empty">
                <div class="a-empty__title">{{ __('directory.empty_title') }}</div>
                <div class="a-empty__body">{{ __('directory.empty_body') }}</div>
            </div>
        @else
            <div class="a-tablewrap">
                <table class="a-table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('directory.col_name') }}</th>
                            <th scope="col">{{ __('directory.col_role') }}</th>
                            <th scope="col">{{ __('directory.col_authority') }}</th>
                            <th scope="col">{{ __('directory.col_sectors') }}</th>
                            <th scope="col">{{ __('directory.col_contact') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($contacts as $contact)
                            <tr>
                                <th scope="row">{{ $contact->name }}</th>
                                <td>{{ $contact->role }}</td>
                                <td>{{ $contact->authority?->acronym ?: $contact->authority?->name }}<span class="a-table__sub">{{ $contact->country?->name }}</span></td>
                                <td>{{ $contact->sectors->pluck('name')->implode(', ') }}</td>
                                <td>
                                    @if ($contact->email)
                                        <a href="mailto:{{ $contact->email }}">{{ $contact->email }}</a>
                                    @endif
                                    @if ($contact->phone)
                                        <span class="a-table__sub">{{ $contact->phone }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{ $contacts->links() }}

    @if ($mine->isNotEmpty() || auth()->user()->country_id !== null)
        <section class="a-card a-card--flush">
            <div class="a-card__head">
                <h2>{{ __('directory.your_people') }}</h2>
            </div>

            <p class="a-hint">{{ __('directory.your_people_note') }}</p>

            @foreach ($mine as $contact)
                <form class="a-form" method="POST" action="{{ route('directory.update', ['locale' => app()->getLocale(), 'contact' => $contact->id]) }}">
                    @csrf
                    @method('PUT')

                    <fieldset class="a-fieldset">
                        <legend class="a-legend">
                            {{ $contact->name }}
                            <span class="a-pill" data-status="{{ $contact->isListed() ? 'validated' : 'draft' }}">{{ $contact->isListed() ? __('directory.listed') : __('directory.not_listed') }}</span>
                        </legend>

                        @include('partials.directory-fields', ['contact' => $contact, 'sectors' => $sectors])
                    </fieldset>

                    <div class="a-form__actions">
                        <button class="a-btn a-btn--primary" type="submit">{{ __('directory.save') }}</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('directory.destroy', ['locale' => app()->getLocale(), 'contact' => $contact->id]) }}">
                    @csrf
                    @method('DELETE')
                    <button class="a-linkbutton a-linkbutton--danger" type="submit">{{ __('directory.remove') }}</button>
                </form>
            @endforeach

            <form class="a-form" method="POST" action="{{ route('directory.store', ['locale' => app()->getLocale()]) }}">
                @csrf

                <fieldset class="a-fieldset">
                    <legend class="a-legend">{{ __('directory.add') }}</legend>
                    @include('partials.directory-fields', ['contact' => null, 'sectors' => $sectors])
                </fieldset>

                <div class="a-form__actions">
                    <button class="a-btn a-btn--primary" type="submit">{{ __('directory.add') }}</button>
                </div>
            </form>
        </section>
    @endif
@endsection

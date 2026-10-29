@extends('layouts.admin')

{{--
    The seats on one committee.

    This is what the confirmation session in Lilongwe writes into. The
    seeder has never filled it in, deliberately -- inventing which member
    state sits on which committee is the one thing it must not do -- so the
    committees page has told every reader "seats not yet recorded" and there
    has been no way to record them.
--}}

@section('title', __('admin.seats_title', ['committee' => $committee->displayName()]))
@section('note', __('admin.seats_note'))

@section('crumbs')
    <li><a href="{{ route('admin.resources', ['locale' => app()->getLocale(), 'resource' => 'committees']) }}">{{ __('admin.nav_committees') }}</a></li>
    <li aria-current="page">{{ $committee->displayName() }}</li>
@endsection

@section('actions')
    <a class="a-btn a-btn--secondary" href="{{ route('admin.resources.edit', ['locale' => app()->getLocale(), 'resource' => 'committees', 'id' => $committee->id]) }}">{{ __('admin.seats_edit_committee') }}</a>
@endsection

@section('content')
    <section class="a-card a-card--flush">
        <div class="a-card__head">
            <h2>{{ __('admin.seats_held') }}</h2>
            <span class="a-card__meta">{{ trans_choice('admin.seats_count', $seated->count(), ['count' => $seated->count()]) }}</span>
        </div>

        @if ($seated->isEmpty())
            <div style="padding:0 var(--s-5) var(--s-5)">
                <div class="a-empty">
                    <div class="a-empty__title">{{ __('admin.seats_empty_title') }}</div>
                    <div class="a-empty__body">{{ __('admin.seats_empty_body') }}</div>
                </div>
            </div>
        @else
            <div class="a-tablewrap">
                <table class="a-table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('admin.seats_col_state') }}</th>
                            <th scope="col">{{ __('admin.seats_col_role') }}</th>
                            <th scope="col">{{ __('admin.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($seated as $country)
                            <tr>
                                <th scope="row">{{ $country->displayName() }}</th>
                                <td>
                                    {{-- The role is changed in place. A
                                         separate edit screen for one select
                                         is a page load for a decision the
                                         operator has already made. --}}
                                    <form class="a-seatform" method="POST"
                                          action="{{ route('admin.committees.seats.update', ['locale' => app()->getLocale(), 'committee' => $committee->slug, 'country' => $country->iso_code]) }}">
                                        @csrf @method('PUT')
                                        <label class="c-sr" for="role-{{ $country->id }}">{{ __('admin.seats_role_for', ['country' => $country->displayName()]) }}</label>
                                        <select class="a-select" id="role-{{ $country->id }}" name="role">
                                            @foreach ($roles as $role)
                                                <option value="{{ $role->value }}" @selected($country->pivot->role === $role->value)>{{ $role->label() }}</option>
                                            @endforeach
                                        </select>
                                        <button class="a-btn a-btn--secondary a-btn--small" type="submit">{{ __('admin.seats_save_role') }}</button>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST"
                                          action="{{ route('admin.committees.seats.destroy', ['locale' => app()->getLocale(), 'committee' => $committee->slug, 'country' => $country->iso_code]) }}"
                                          data-confirm="{{ __('admin.seats_remove_confirm', ['country' => $country->displayName(), 'committee' => $committee->displayName()]) }}" data-confirm-title="{{ __('fa.confirm_title') }}">
                                        @csrf @method('DELETE')
                                        <button class="a-btn a-btn--danger a-btn--small" type="submit">{{ __('admin.seats_remove') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @if ($available->isNotEmpty())
        <section class="a-card">
            <div class="a-card__head">
                <h2>{{ __('admin.seats_add') }}</h2>
            </div>

            <form class="a-form" method="POST"
                  action="{{ route('admin.committees.seats.store', ['locale' => app()->getLocale(), 'committee' => $committee->slug]) }}">
                @csrf

                <div class="a-field">
                    <label class="a-label" for="country_id">
                        {{ __('admin.seats_col_state') }}
                        <span class="a-required" aria-hidden="true">*</span>
                        <span class="c-sr">{{ __('admin.required') }}</span>
                    </label>
                    {{-- Only the states that do not already hold a seat.
                         Offering one that does turns "add" into a silent
                         change of role. --}}
                    <select class="a-select" id="country_id" name="country_id" required>
                        @foreach ($available as $country)
                            <option value="{{ $country->id }}" @selected((int) old('country_id') === $country->id)>{{ $country->displayName() }}</option>
                        @endforeach
                    </select>
                    @error('country_id')<p class="c-field__error">{{ $message }}</p>@enderror
                </div>

                <div class="a-field">
                    <label class="a-label" for="role">
                        {{ __('admin.seats_col_role') }}
                        <span class="a-required" aria-hidden="true">*</span>
                        <span class="c-sr">{{ __('admin.required') }}</span>
                    </label>
                    <select class="a-select" id="role" name="role" required>
                        @foreach ($roles as $role)
                            <option value="{{ $role->value }}" @selected(old('role') === $role->value)>{{ $role->label() }}</option>
                        @endforeach
                    </select>
                    <p class="a-hint">{{ __('admin.seats_chair_hint') }}</p>
                    @error('role')<p class="c-field__error">{{ $message }}</p>@enderror
                </div>

                <div class="a-form__actions">
                    <button class="a-btn a-btn--primary" type="submit">{{ __('admin.seats_add_action') }}</button>
                </div>
            </form>
        </section>
    @endif

    {{-- Day 3. The people on the committee, from the platform's accounts:
         add one, set or change the chair, remove a seat. Same rules as the
         member state seats above, and the same audit rows. --}}
    <section class="a-card a-card--flush" id="people">
        <div class="a-card__head">
            <h2>{{ __('d3_crud.people_title') }}</h2>
            <span class="a-card__meta">{{ trans_choice('d3_crud.people_count', $people->count(), ['count' => $people->count()]) }}</span>
        </div>

        @if ($people->isEmpty())
            <div style="padding:0 var(--s-5) var(--s-5)">
                <div class="a-empty">
                    <div class="a-empty__title">{{ __('d3_crud.people_empty_title') }}</div>
                    <div class="a-empty__body">{{ __('d3_crud.people_empty_body') }}</div>
                </div>
            </div>
        @else
            <div class="a-tablewrap">
                <table class="a-table">
                    <caption class="c-sr">{{ __('d3_crud.people_title') }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">{{ __('d3_crud.people_col_person') }}</th>
                            <th scope="col">{{ __('admin.seats_col_role') }}</th>
                            <th scope="col">{{ __('admin.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($people as $person)
                            <tr>
                                <th scope="row">{{ $person->name }}</th>
                                <td>
                                    <form class="a-seatform" method="POST"
                                          action="{{ route('admin.committees.members.update', ['locale' => app()->getLocale(), 'committee' => $committee->slug, 'user' => $person->id]) }}">
                                        @csrf @method('PUT')
                                        <label class="c-sr" for="person-role-{{ $person->id }}">{{ __('d3_crud.people_role_for', ['name' => $person->name]) }}</label>
                                        <select class="a-select" id="person-role-{{ $person->id }}" name="role">
                                            @foreach ($roles as $role)
                                                <option value="{{ $role->value }}" @selected($person->pivot->role === $role->value)>{{ __('d3_crud.role_'.$role->value) }}</option>
                                            @endforeach
                                        </select>
                                        <button class="a-btn a-btn--secondary a-btn--small" type="submit">{{ __('admin.seats_save_role') }}</button>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST"
                                          action="{{ route('admin.committees.members.destroy', ['locale' => app()->getLocale(), 'committee' => $committee->slug, 'user' => $person->id]) }}"
                                          data-confirm="{{ __('d3_crud.people_remove_confirm', ['name' => $person->name, 'committee' => $committee->displayName()]) }}" data-confirm-title="{{ __('fa.confirm_title') }}">
                                        @csrf @method('DELETE')
                                        <button class="a-btn a-btn--danger a-btn--small" type="submit">{{ __('admin.seats_remove') }}<span class="c-sr"> {{ $person->name }}</span></button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @if ($availableUsers->isNotEmpty())
        <section class="a-card">
            <div class="a-card__head">
                <h2>{{ __('d3_crud.people_add') }}</h2>
            </div>

            <form class="a-form" method="POST"
                  action="{{ route('admin.committees.members.store', ['locale' => app()->getLocale(), 'committee' => $committee->slug]) }}">
                @csrf

                <div class="a-field">
                    <label class="a-label" for="user_id">
                        {{ __('d3_crud.people_col_person') }}
                        <span class="a-required" aria-hidden="true">*</span>
                        <span class="c-sr">{{ __('admin.required') }}</span>
                    </label>
                    <select class="a-select" id="user_id" name="user_id" required>
                        @foreach ($availableUsers as $candidate)
                            <option value="{{ $candidate->id }}" @selected((int) old('user_id') === $candidate->id)>{{ $candidate->name }} ({{ $candidate->email }})</option>
                        @endforeach
                    </select>
                    @error('user_id')<p class="c-field__error">{{ $message }}</p>@enderror
                </div>

                <div class="a-field">
                    <label class="a-label" for="person_role">
                        {{ __('admin.seats_col_role') }}
                        <span class="a-required" aria-hidden="true">*</span>
                        <span class="c-sr">{{ __('admin.required') }}</span>
                    </label>
                    <select class="a-select" id="person_role" name="role" required aria-describedby="person_role_hint">
                        @foreach ($roles as $role)
                            <option value="{{ $role->value }}">{{ __('d3_crud.role_'.$role->value) }}</option>
                        @endforeach
                    </select>
                    <p class="a-hint" id="person_role_hint">{{ __('d3_crud.people_chair_hint') }}</p>
                </div>

                <div class="a-form__actions">
                    <button class="a-btn a-btn--primary" type="submit">{{ __('d3_crud.people_add_action') }}</button>
                </div>
            </form>
        </section>
    @endif
@endsection

@extends('layouts.admin')

{{--
    One proposal, beside what the platform currently holds.

    Side by side, because the question the Secretariat is answering is not
    "is this right" but "is this a change worth making", and that cannot be
    answered without seeing what it replaces.
--}}

@section('title', $change->country->name)
@section('note', __('authority.review_title'))

@section('crumbs')
    <li><a href="{{ route('admin.authority-changes', ['locale' => app()->getLocale()]) }}">{{ __('authority.review_title') }}</a></li>
    <li aria-current="page">{{ $change->country->name }}</li>
@endsection

@section('content')
    <section class="a-card a-card--flush">
        <div class="a-card__head">
            <h2>{{ __('authority.proposed_by', ['name' => $change->proposer?->name ?? '—']) }}</h2>
            <span class="a-card__meta">{{ $change->submitted_at?->displayed()->isoFormat('D MMMM YYYY') }}</span>
        </div>

        <div class="a-tablewrap">
            <table class="a-table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('authority.field') }}</th>
                        <th scope="col">{{ __('authority.held_now') }}</th>
                        <th scope="col">{{ __('authority.proposed') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (['name', 'acronym', 'website', 'established_year'] as $field)
                        <tr>
                            <th scope="row">{{ __('authority.field_'.($field === 'established_year' ? 'established' : $field)) }}</th>
                            <td>{{ $current[$field] ?? '—' }}</td>
                            <td>{{ $change->payload[$field] ?? '—' }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <th scope="row">{{ __('authority.mandate') }}</th>
                        <td>{{ collect($current['sectors'] ?? [])->map(fn ($id) => $sectors[$id]?->name)->filter()->implode(', ') ?: '—' }}</td>
                        <td>{{ collect($change->payload['sectors'] ?? [])->map(fn ($id) => $sectors[$id]?->name)->filter()->implode(', ') ?: '—' }}</td>
                    </tr>
                    <tr>
                        <th scope="row">{{ __('authority.seats') }}</th>
                        <td>{{ collect($current['committees'] ?? [])->map(fn ($id) => $committees[$id]?->name)->filter()->implode(', ') ?: '—' }}</td>
                        <td>{{ collect($change->payload['committees'] ?? [])->map(fn ($id) => $committees[$id]?->name)->filter()->implode(', ') ?: '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    @if ($change->isOpen())
        <section class="a-card">
            <div class="a-card__head">
                <h2>{{ __('authority.approve_action') }}</h2>
            </div>

            <form class="a-form" method="POST" action="{{ route('admin.authority-changes.approve', ['locale' => app()->getLocale(), 'change' => $change->id]) }}">
                @csrf
                @method('PUT')

                {{-- The delegate's own name, not the operator's account.
                     Same rule as the confirmation session: a confirmation
                     that names the person driving the projector rather than
                     the person vouching is a confirmation of nothing. --}}
                <div class="a-field">
                    <label class="a-label" for="confirmed_by_name">{{ __('authority.confirmed_by_label') }}<span class="a-required">{{ __('admin.required') }}</span></label>
                    <input class="a-input" type="text" id="confirmed_by_name" name="confirmed_by_name" maxlength="160" required
                           value="{{ old('confirmed_by_name', $change->proposer?->name) }}">
                    <p class="a-hint">{{ __('authority.confirmed_by_hint') }}</p>
                    @error('confirmed_by_name')<p class="a-hint" role="alert">{{ $message }}</p>@enderror
                </div>

                <div class="a-form__actions">
                    <button class="a-btn a-btn--primary" type="submit">{{ __('authority.approve_action') }}</button>
                </div>
            </form>
        </section>

        <section class="a-card">
            <div class="a-card__head">
                <h2>{{ __('authority.return_action') }}</h2>
            </div>

            <form class="a-form" method="POST" action="{{ route('admin.authority-changes.return', ['locale' => app()->getLocale(), 'change' => $change->id]) }}">
                @csrf
                @method('PUT')

                <div class="a-field">
                    <label class="a-label" for="review_note">{{ __('authority.return_label') }}<span class="a-required">{{ __('admin.required') }}</span></label>
                    <textarea class="a-input" id="review_note" name="review_note" rows="4" maxlength="2000" required>{{ old('review_note') }}</textarea>
                    @error('review_note')<p class="a-hint" role="alert">{{ $message }}</p>@enderror
                </div>

                <div class="a-form__actions">
                    <button class="a-btn a-btn--secondary" type="submit">{{ __('authority.return_action') }}</button>
                </div>
            </form>
        </section>
    @endif
@endsection

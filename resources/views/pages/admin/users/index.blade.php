@extends('layouts.public')
@section('title', __('admin.users_title'))

@section('content')
    <div class="c-section">
        <div class="c-page">
            <h1>{{ __('admin.users_title') }}</h1>

            @if (session('status'))
                <div class="c-notice c-notice--success" role="status">
                    <div><div class="c-notice__body">{{ session('status') }}</div></div>
                </div>
            @endif

            @if ($unenrolledCount > 0)
                <div class="c-notice c-notice--pending" role="status">
                    <div>
                        <div class="c-notice__title">{{ trans_choice('admin.unenrolled_title', $unenrolledCount, ['count' => $unenrolledCount]) }}</div>
                        <div class="c-notice__body">
                            {{ __('admin.unenrolled_body') }}
                            <a href="{{ request()->fullUrlWithQuery(['unenrolled' => 1]) }}">{{ __('admin.filter_to_them') }}</a>
                        </div>
                    </div>
                </div>
            @endif

            <p><a class="c-btn c-btn--primary" href="{{ route('admin.users.form', ['locale' => app()->getLocale()]) }}">{{ __('admin.invite') }}</a></p>

            <div class="c-scroll">
                <table class="c-table">
                    <caption class="c-sr">{{ __('admin.users_title') }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">{{ __('account.name') }}</th>
                            <th scope="col">{{ __('account.role') }}</th>
                            <th scope="col">{{ __('account.status') }}</th>
                            <th scope="col">{{ __('auth.two_factor') }}</th>
                            <th scope="col">{{ __('admin.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $account)
                            <tr>
                                <th scope="row">
                                    {{ $account->name }}
                                    <span class="c-table__sub">{{ $account->email }}</span>
                                    @if ($account->country)<span class="c-table__sub">{{ $account->country->name }}</span>@endif
                                </th>
                                <td>
                                    <form method="POST" action="{{ route('admin.users.role', ['locale' => app()->getLocale(), 'user' => $account->id]) }}" class="c-inline-form">
                                        @csrf
                                        @method('PUT')
                                        <label class="c-sr" for="role-{{ $account->id }}">{{ __('admin.change_role_for', ['name' => $account->name]) }}</label>
                                        <select class="c-input c-input--compact" id="role-{{ $account->id }}" name="role" @disabled(auth()->id() === $account->id)>
                                            @foreach ($roles as $role)
                                                <option value="{{ $role->value }}" @selected($account->role === $role)>{{ $role->label() }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="c-btn c-btn--tertiary" @disabled(auth()->id() === $account->id)>{{ __('admin.apply') }}</button>
                                    </form>
                                </td>
                                <td>
                                    <span class="c-pill {{ $account->isSuspended() ? 'c-pill--error' : ($account->status->value === 'invited' ? 'c-pill--pending' : 'c-pill--validated') }}">
                                        {{ $account->status->label() }}
                                    </span>
                                </td>
                                <td>
                                    @if ($account->hasConfirmedTwoFactor())
                                        <span class="c-pill c-pill--validated">{{ __('admin.enrolled') }}</span>
                                    @else
                                        <span class="c-pill {{ $account->role->requiresTwoFactor() ? 'c-pill--error' : 'c-pill--archived' }}">{{ __('admin.not_enrolled') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if (auth()->id() !== $account->id)
                                        <form method="POST" action="{{ route($account->isSuspended() ? 'admin.users.reactivate' : 'admin.users.suspend', ['locale' => app()->getLocale(), 'user' => $account->id]) }}">
                                            @csrf
                                            @method('PUT')
                                            <button type="submit" class="c-btn c-btn--tertiary">
                                                {{ $account->isSuspended() ? __('admin.reactivate') : __('admin.suspend') }}
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="c-pagination">{{ $users->links() }}</div>

            <h2>{{ __('admin.matrix_title') }}</h2>
            <p class="c-small">{{ __('admin.matrix_note') }}</p>
            <div class="c-scroll">
                <table class="c-table c-table--matrix">
                    <caption class="c-sr">{{ __('admin.matrix_title') }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">{{ __('admin.permission') }}</th>
                            @foreach ($roles as $role)
                                <th scope="col">{{ $role->label() }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($permissions as $permission)
                            <tr>
                                <th scope="row">{{ $permission->value }}</th>
                                @foreach ($roles as $role)
                                    @php $grant = $matrix::grant($role, $permission); @endphp
                                    <td class="is-{{ $grant->value }}">
                                        {{-- The mark states the state as a character as well as a
                                             colour, so nothing here depends on colour alone. --}}
                                        <span aria-hidden="true">{{ $grant->mark() }}</span>
                                        <span class="c-sr">{{ __('admin.grant_'.$grant->value) }}</span>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

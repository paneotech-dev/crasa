@extends('layouts.admin')
@section('title', __('admin.users_title'))

@section('note', __('admin.users_note'))

@section('actions')
    <a class="a-btn a-btn--primary" href="{{ route('admin.users.form', ['locale' => app()->getLocale()]) }}">{{ __('admin.invite') }}</a>
@endsection

@section('content')
    @if ($unenrolledCount > 0)
        <div class="a-notice a-notice--pending" role="status">
            <strong>{{ trans_choice('admin.unenrolled_title', $unenrolledCount, ['count' => $unenrolledCount]) }}</strong>
            {{ __('admin.unenrolled_body') }}
            <a href="{{ request()->fullUrlWithQuery(['unenrolled' => 1]) }}">{{ __('admin.filter_to_them') }}</a>
        </div>
    @endif

    <section class="a-resource">
        <div class="a-tablewrap">
            <table class="a-table">
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
                    <span class="a-table__sub">{{ $account->email }}</span>
                    @if ($account->country)<span class="a-table__sub">{{ $account->country->name }}</span>@endif
                </th>
                <td>
                    <form method="POST" action="{{ route('admin.users.role', ['locale' => app()->getLocale(), 'user' => $account->id]) }}" class="a-form__actions">
                        @csrf
                        @method('PUT')
                        <label class="c-sr" for="role-{{ $account->id }}">{{ __('admin.change_role_for', ['name' => $account->name]) }}</label>
                        <select class="a-select" id="role-{{ $account->id }}" name="role" @disabled(auth()->id() === $account->id)>
                    @foreach ($roles as $role)
                        <option value="{{ $role->value }}" @selected($account->role === $role)>{{ $role->label() }}</option>
                    @endforeach
                        </select>
                        <button type="submit" class="a-btn a-btn--secondary" @disabled(auth()->id() === $account->id)>{{ __('admin.apply') }}</button>
                    </form>
                </td>
                <td>
                    <span class="a-pill" data-status="{{ $account->isSuspended() ? 'suspended' : ($account->status->value === 'invited' ? 'pending' : 'validated') }}">
                        {{ $account->status->label() }}
                    </span>
                </td>
                <td>
                    @if ($account->hasConfirmedTwoFactor())
                        <span class="a-pill" data-status="validated">{{ __('admin.enrolled') }}</span>
                    @else
                        <span class="a-pill" data-status="{{ $account->role->requiresTwoFactor() ? 'rejected' : 'archived' }}">{{ __('admin.not_enrolled') }}</span>
                    @endif
                </td>
                <td>
                    @if (auth()->id() !== $account->id)
                        <form method="POST" action="{{ route($account->isSuspended() ? 'admin.users.reactivate' : 'admin.users.suspend', ['locale' => app()->getLocale(), 'user' => $account->id]) }}">
                    @csrf
                    @method('PUT')
                    <button type="submit" class="a-btn a-btn--secondary">
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
        <div class="a-pager">{{ $users->links() }}</div>
    </section>

    <section class="a-resource">
        <div class="a-resource__head">
            <h2 class="a-resource__title">{{ __('admin.matrix_title') }}</h2>
            <div class="a-resource__note">{{ __('admin.matrix_note') }}</div>
        </div>
        <div class="a-tablewrap">
            <table class="a-table a-table--matrix">
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
                <th scope="row">{{ $permission->label() }}</th>
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
    </section>
@endsection

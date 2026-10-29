@extends('layouts.admin')
@section('title', __('admin.users_title'))

@section('note', __('admin.users_note'))

@section('actions')
    @can(\App\Enums\Permission::AssignRoles->value)
        <a class="a-btn a-btn--secondary" href="{{ route('admin.roles', ['locale' => app()->getLocale()]) }}">{{ __('fa.roles_title') }}</a>
    @endcan
    <a class="a-btn a-btn--primary" href="{{ route('admin.users.form', ['locale' => app()->getLocale()]) }}">{{ __('admin.invite') }}</a>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/d3-people.css') }}">
@endpush

@section('content')
    {{-- While two-factor is switched off for every account the banner
         describes a requirement that is not in force. --}}
    @if ($unenrolledCount > 0 && \App\Enums\Role::Administrator->requiresTwoFactor())
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
                    <a href="{{ route('admin.users.show', ['locale' => app()->getLocale(), 'user' => $account->id]) }}">{{ $account->name }}</a>
                    <span class="a-table__sub">{{ $account->email }}</span>
                    @if ($account->country)<span class="a-table__sub">{{ $account->country->name }}</span>@endif
                </th>
                <td>
                    <form method="POST" action="{{ route('admin.users.role', ['locale' => app()->getLocale(), 'user' => $account->id]) }}" class="a-form__actions">
                        @csrf
                        @method('PUT')
                        <label class="c-sr" for="role-{{ $account->id }}">{{ __('admin.change_role_for', ['name' => $account->name]) }}</label>
                        <select class="a-select" id="role-{{ $account->id }}" name="role" @disabled(auth()->id() === $account->id)>
                    @foreach ($roleOptions as $option)
                        <option value="{{ $option->key }}" @selected(($account->customRole?->key ?? $account->role->value) === $option->key)>{{ $option->label() }}</option>
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
                    <span class="d3p-actions">
                    <a class="a-btn a-btn--secondary a-btn--small" href="{{ route('admin.users.edit', ['locale' => app()->getLocale(), 'user' => $account->id]) }}">{{ __('d3_people.edit') }}<span class="c-sr"> {{ $account->name }}</span></a>
                    @if (auth()->id() === $account->id)
                        {{-- Nothing below applies to your own account; say why the row has no more actions. --}}
                        <span class="d3p-you">{{ __('d3_people.you') }}</span>
                    @else
                        <form method="POST" action="{{ route($account->isSuspended() ? 'admin.users.reactivate' : 'admin.users.suspend', ['locale' => app()->getLocale(), 'user' => $account->id]) }}">
                            @csrf
                            @method('PUT')
                            <button type="submit" class="a-btn a-btn--secondary a-btn--small">
                                {{ $account->isSuspended() ? __('admin.reactivate') : __('admin.suspend') }}<span class="c-sr"> {{ $account->name }}</span>
                            </button>
                        </form>
                        @can('delete', $account)
                            @if ($account->isSuspended())
                                <form method="POST" action="{{ route('admin.users.destroy', ['locale' => app()->getLocale(), 'user' => $account->id]) }}"
                                      data-confirm="{{ __('fa.user_delete_confirm', ['name' => $account->name]) }}"
                                      data-confirm-title="{{ __('fa.user_delete_title') }}" data-confirm-action="{{ __('fa.delete') }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="a-btn a-btn--danger a-btn--small">{{ __('fa.delete') }}<span class="c-sr"> {{ $account->name }}</span></button>
                                </form>
                            @else
                                {{-- Deleting comes after suspending. The control is shown so the
                                     rule is visible; aria-disabled keeps it focusable so a screen
                                     reader reaches it and reads the reason. --}}
                                <button type="button" class="a-btn a-btn--danger a-btn--small" aria-disabled="true" aria-describedby="del-why-{{ $account->id }}">{{ __('fa.delete') }}<span class="c-sr"> {{ $account->name }}</span></button>
                                <span class="d3p-why" id="del-why-{{ $account->id }}">{{ __('d3_people.suspend_first') }}</span>
                            @endif
                        @endcan
                    @endif
                    </span>
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

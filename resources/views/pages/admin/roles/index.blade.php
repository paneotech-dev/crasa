@extends('layouts.admin')
@section('title', __('fa.roles_title'))
@section('note', __('fa.roles_note'))

@section('crumbs')
    <li><a href="{{ route('admin.users', ['locale' => app()->getLocale()]) }}">{{ __('admin.users_title') }}</a></li>
    <li aria-current="page">{{ __('fa.roles_title') }}</li>
@endsection

@section('actions')
    <a class="a-btn a-btn--primary" href="{{ route('admin.roles.create', ['locale' => app()->getLocale()]) }}">{{ __('fa.roles_new') }}</a>
@endsection

@section('content')
    @php $locale = app()->getLocale(); @endphp
    @error('role')
        <p class="a-notice a-notice--error" role="alert">{{ $message }}</p>
    @enderror

    <div class="a-tablewrap">
        <table class="a-table aw-table">
            <caption class="c-sr">{{ __('fa.roles_title') }}</caption>
            <thead><tr>
                <th scope="col">{{ __('fa.roles_col_role') }}</th>
                <th scope="col">{{ __('fa.roles_col_users') }}</th>
                <th scope="col">{{ __('fa.roles_col_kind') }}</th>
                <th scope="col">{{ __('admin.actions') }}</th>
            </tr></thead>
            <tbody>
            @foreach ($roles as $role)
                <tr>
                    <th scope="row">{{ $role->label() }}</th>
                    <td class="c-num">{{ $counts[$role->id] }}</td>
                    <td>{{ $role->is_built_in ? __('fa.roles_preset') : __('fa.roles_custom', ['base' => __('roles.'.$role->base_role)]) }}</td>
                    <td>
                        <div class="aw-roles-actions">
                            <a class="a-btn a-btn--small a-btn--secondary" href="{{ route('admin.roles.edit', ['locale' => $locale, 'role' => $role->key]) }}">{{ __('fa.edit') }}<span class="c-sr"> {{ $role->label() }}</span></a>
                            <a class="a-btn a-btn--small a-btn--secondary" href="{{ route('admin.roles.create', ['locale' => $locale, 'copy' => $role->key]) }}">{{ __('fa.roles_copy') }}<span class="c-sr"> {{ $role->label() }}</span></a>
                            @if (! $role->is_built_in && $counts[$role->id] === 0)
                                <form class="aw-inline-form" method="POST" action="{{ route('admin.roles.destroy', ['locale' => $locale, 'role' => $role->key]) }}"
                                      data-confirm="{{ __('fa.roles_delete_confirm', ['name' => $role->label()]) }}"
                                      data-confirm-title="{{ __('fa.roles_delete_title') }}" data-confirm-action="{{ __('fa.delete') }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="a-btn a-btn--small a-btn--danger">{{ __('fa.delete') }}<span class="c-sr"> {{ $role->label() }}</span></button>
                                </form>
                            @elseif ($role->is_built_in)
                                <span class="a-hint">{{ __('fa.roles_cannot_delete_preset') }}</span>
                            @else
                                <span class="a-hint">{{ trans_choice('fa.roles_in_use', $counts[$role->id], ['count' => $counts[$role->id]]) }}</span>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endsection

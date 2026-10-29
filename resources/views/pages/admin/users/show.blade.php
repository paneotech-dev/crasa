@extends('layouts.admin')
@php
    $locale = app()->getLocale();
    $isSelf = auth()->id() === $account->id;
@endphp
@section('title', $account->name)
@section('note', $account->email)

@section('crumbs')
    <li><a href="{{ route('admin.users', ['locale' => $locale]) }}">{{ __('admin.users_title') }}</a></li>
    <li aria-current="page">{{ $account->name }}</li>
@endsection

@section('actions')
    @can('update', $account)
        <a class="a-btn a-btn--primary" href="{{ route('admin.users.edit', ['locale' => $locale, 'user' => $account->id]) }}">{{ __('d3_people.edit') }}<span class="c-sr"> {{ $account->name }}</span></a>
    @endcan
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/d3-people.css') }}">
@endpush

@section('content')
    @if (session('status'))
        <div class="a-notice a-notice--success" role="status">{{ session('status') }}</div>
    @endif

    <section class="a-panel d3p-panel" aria-labelledby="user-details">
        <div class="a-panel__head"><h2 id="user-details">{{ __('d3_people.section_account') }}</h2></div>
        <dl class="d3p-facts">
            <div><dt>{{ __('account.role') }}</dt><dd>{{ $account->roleLabel() }}@if ($isSelf) ({{ __('d3_people.you') }})@endif</dd></div>
            <div><dt>{{ __('account.status') }}</dt><dd>{{ $account->status->label() }}</dd></div>
            <div><dt>{{ __('contact.field_country') }}</dt><dd>{{ $account->country?->name ?? __('admin.secretariat_no_country') }}</dd></div>
            <div><dt>{{ __('d3_people.authority') }}</dt><dd>{{ $account->authority ? $account->authority->acronym.', '.$account->authority->name : __('d3_people.no_authority') }}</dd></div>
            <div><dt>{{ __('d3_people.section_committees') }}</dt><dd>
                @forelse ($account->committees as $committee)
                    {{ $committee->name }} ({{ __('extranet.seat_'.$committee->pivot->role) }})@if (! $loop->last)<br>@endif
                @empty
                    {{ __('d3_people.no_seats') }}
                @endforelse
            </dd></div>
            <div><dt>{{ __('d3_people.last_sign_in') }}</dt><dd>{{ $account->last_login_at?->displayed()->format('Y-m-d H:i') ?? __('d3_people.never') }}</dd></div>
        </dl>
    </section>

    <section class="a-resource" aria-labelledby="user-audit">
        <div class="a-resource__head">
            <h2 class="a-resource__title" id="user-audit">{{ __('d3_people.audit_title') }}</h2>
            <div class="a-resource__note">{{ __('d3_people.audit_intro') }}</div>
        </div>

        <nav class="a-tabs" aria-label="{{ __('d3_people.audit_filter') }}">
            @foreach (['all', 'about', 'by'] as $key)
                <a href="{{ route('admin.users.show', ['locale' => $locale, 'user' => $account->id, 'show' => $key === 'all' ? null : $key]) }}" @if ($show === $key) aria-current="page" @endif>{{ __('d3_people.audit_show_'.$key) }}</a>
            @endforeach
        </nav>

        <p class="c-results d3p-body" role="status">{{ trans_choice('admin.entry_count', $entries->total(), ['count' => $entries->total()]) }}</p>

        @if ($entries->isEmpty())
            <div class="a-empty d3p-body">
                <div class="a-empty__title">{{ __('d3_people.audit_empty_title') }}</div>
                <div class="a-empty__body">{{ __('d3_people.audit_empty_body') }}</div>
            </div>
        @else
            <div class="a-tablewrap">
                <table class="a-table">
                    <caption class="c-sr">{{ __('d3_people.audit_title') }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">{{ __('admin.when') }}</th>
                            <th scope="col">{{ __('admin.actor') }}</th>
                            <th scope="col">{{ __('admin.action') }}</th>
                            <th scope="col">{{ __('d3_people.audit_detail') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($entries as $entry)
                            @php
                                $about = $entry->auditable_type === $account->getMorphClass() && (int) $entry->auditable_id === $account->id;
                                $changes = is_array($entry->changes) ? $entry->changes : [];
                                $fields = $entry->action === \App\Models\AuditLog::UPDATED && ! isset($changes['attributes']) ? array_keys($changes) : [];
                            @endphp
                            <tr>
                                <td class="c-num">{{ $entry->created_at?->displayed()->format('Y-m-d H:i') }}</td>
                                <td>{{ $entry->user?->anonymised_at ? __('fa.former_user') : ($entry->user?->name ?? __('admin.no_actor')) }}</td>
                                <th scope="row">
                                    {{ $entry->describe() }}
                                    <span class="a-table__sub">{{ (int) $entry->user_id === $account->id ? __('d3_people.by_this_account') : __('d3_people.about_this_account') }}</span>
                                </th>
                                <td>
                                    @if ($entry->action === \App\Models\AuditLog::ROLE_CHANGED && isset($changes['from'], $changes['to']))
                                        {{ __('d3_people.role_from_to', ['from' => $changes['from'], 'to' => $changes['to']]) }}
                                    @elseif ($fields !== [])
                                        {{ __('d3_people.fields_changed', ['fields' => implode(', ', $fields)]) }}
                                    @elseif (! $about && $entry->auditable_type)
                                        {{ class_basename($entry->auditable_type) }} #{{ $entry->auditable_id }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="a-pager">{{ $entries->links() }}</div>
        @endif
    </section>
@endsection

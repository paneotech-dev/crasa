@extends('layouts.admin')

@section('title', $definition->label())
@section('note', __('admin.resourcenote_'.$definition->key))

@section('crumbs')
    <li><a href="{{ route('admin.dashboard', ['locale' => app()->getLocale()]) }}">{{ __('admin.section_dashboard') }}</a></li>
    <li aria-current="page">{{ $definition->label() }}</li>
@endsection

@section('actions')
    @if ($definition->softDeletes() && $deletedCount > 0)
        <a class="a-btn a-btn--secondary" href="{{ route('admin.resources', ['locale' => app()->getLocale(), 'resource' => $definition->key, 'deleted' => $showingDeleted ? null : 1]) }}">
            {{ $showingDeleted ? __('admin.show_current') : trans_choice('admin.show_deleted', $deletedCount, ['count' => $deletedCount]) }}
        </a>
    @endif
    @if ($definition->creatable)
        @can('create', $definition->model)
            <a class="a-btn a-btn--primary" href="{{ route('admin.resources.create', ['locale' => app()->getLocale(), 'resource' => $definition->key]) }}">
                {{ __('admin.resource_new', ['name' => $definition->singular()]) }}
            </a>
        @endcan
    @endif
@endsection

@section('content')
    @if ($definition->isReferenceData)
        {{-- Reference data is what every other record points at. Saying so
             here is cheaper than explaining an orphaned foreign key. --}}
        <div class="a-notice a-notice--pending" role="note">{{ $definition->deletable ? __('d3_crud.reference_data_note') : __('admin.reference_data_note') }}</div>
    @endif

    {{-- A refused delete from a row of this list comes back here. --}}
    @error('record')
        <div class="a-notice a-notice--error" role="alert">{{ $message }}</div>
    @enderror

    @if ($showingDeleted)
        <div class="a-notice a-notice--pending" role="status">{{ __('admin.showing_deleted') }}</div>
    @endif

    <x-admin.resource-table
        :title="$definition->label()"
        :columns="collect($definition->columns)->map(fn ($c) => [
            'key' => $c,
            'label' => __('admin.column_'.$c) === 'admin.column_'.$c ? __('admin.field_'.$c) : __('admin.column_'.$c),
        ])->all()"
        :rows="$records"
        :searchLabel="__('admin.resource_search', ['name' => strtolower($definition->label())])"
        :emptyTitle="__('admin.resource_empty_title', ['name' => strtolower($definition->label())])"
        :emptyBody="$definition->creatable ? __('admin.resource_empty_body_create') : __('admin.resource_empty_body')"
        :row="fn ($r) => [
            'url' => $showingDeleted ? null : route('admin.resources.edit', ['locale' => app()->getLocale(), 'resource' => $definition->key, 'id' => $r->getKey()]),
            'cells' => collect($definition->columns)->mapWithKeys(fn ($c) => [
                $c => \App\Support\ResourceCell::render($definition, $r, $c),
            ])->all(),
            'actions' => $showingDeleted ? [] : array_values(array_filter([
                ['label' => __('admin.edit'), 'url' => route('admin.resources.edit', ['locale' => app()->getLocale(), 'resource' => $definition->key, 'id' => $r->getKey()]), 'sr' => $definition->titleFor($r)],
                $definition->deletable && auth()->user()->can('delete', $r)
                    ? ($definition->softDeletes()
                        ? ['label' => __('fa.delete'), 'method' => 'DELETE', 'sr' => $definition->titleFor($r),
                           'url' => route('admin.resources.destroy', ['locale' => app()->getLocale(), 'resource' => $definition->key, 'id' => $r->getKey()]),
                           'confirm' => __('fa.delete_recoverable_confirm', ['name' => $definition->titleFor($r)])]
                        : ['label' => __('fa.delete'), 'sr' => $definition->titleFor($r), 'url' => route('admin.resources.edit', ['locale' => app()->getLocale(), 'resource' => $definition->key, 'id' => $r->getKey()]).'#delete'])
                    : null,
            ])),
        ]">
        @if ($filters !== [])
            <x-slot:filters>
                @foreach ($filters as $filter)
                    <label class="c-sr" for="filter-{{ $filter['key'] }}">{{ __('office.filter_'.$filter['key']) }}</label>
                    <select class="a-filter" id="filter-{{ $filter['key'] }}" name="{{ $filter['key'] }}">
                        <option value="">{{ __('office.filter_all_'.$filter['key']) }}</option>
                        @foreach ($filter['options'] as $value => $label)
                            <option value="{{ $value }}" @selected($filter['value'] === (string) $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                @endforeach
            </x-slot:filters>
        @endif
    </x-admin.resource-table>

    @if ($showingDeleted)
        <section class="a-panel">
            <div class="a-panel__head">
                <h2>{{ __('admin.restore_title') }}</h2>
                <span class="a-panel__meta">{{ __('admin.restore_note') }}</span>
            </div>
            <div class="a-rows">
                @foreach ($records as $record)
                    <div class="a-row">
                        <span class="a-row__grow">{{ $definition->titleFor($record) }}</span>
                        <span class="a-row__time">{{ $record->deleted_at?->isoFormat('D MMM YYYY') }}</span>
                        @can('restore', $record)
                            <form method="POST" action="{{ route('admin.resources.restore', ['locale' => app()->getLocale(), 'resource' => $definition->key, 'id' => $record->getKey()]) }}">
                                @csrf
                                @method('PUT')
                                <button type="submit" class="a-linkbutton">{{ __('admin.restore') }}</button>
                            </form>
                        @endcan
                    </div>
                @endforeach
            </div>
        </section>
    @endif
@endsection

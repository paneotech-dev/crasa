@extends('layouts.admin')

@php $isNew = ! $record->exists; @endphp

@section('title', $isNew ? __('admin.resource_new', ['name' => $definition->singular()]) : $definition->titleFor($record))
@section('note', $definition->label())

@section('crumbs')
    <li><a href="{{ route('admin.resources', ['locale' => app()->getLocale(), 'resource' => $definition->key]) }}">{{ $definition->label() }}</a></li>
    <li aria-current="page">{{ $isNew ? __('admin.resource_new', ['name' => $definition->singular()]) : $definition->titleFor($record) }}</li>
@endsection

{{-- Committees carry seats, which are a separate screen: fourteen member
     states against three roles is a table, not four more fields on a form.
     One conditional rather than a per-resource hook, because this is the
     only resource that has one. --}}
@if ($definition->key === 'committees' && ! $isNew)
    @section('actions')
        <a class="a-btn a-btn--secondary" href="{{ route('admin.committees.seats', ['locale' => app()->getLocale(), 'committee' => $record->slug]) }}">{{ __('admin.seats_manage') }}</a>
    @endsection
@endif

{{-- An instrument carries a version history, which is a screen of its
     own: two versions side by side is not four more fields on a form. --}}
@if ($definition->key === 'instruments' && ! $isNew)
    @section('actions')
        <a class="a-btn a-btn--secondary" href="{{ route('admin.instruments.versions', ['locale' => app()->getLocale(), 'instrument' => $record->slug]) }}">{{ __('admin.versions_title') }}</a>
    @endsection
@endif

@section('content')
    @if (! $isNew && $definition->softDeletes() && $record->trashed())
        <div class="a-band">
            <strong>{{ __('admin.record_deleted_title') }}</strong>
            {{ __('admin.record_deleted_body') }}
        </div>
    @endif

    @include('pages.admin.approvals._panel', ['record' => $record])

    <form method="POST" action="{{ $isNew
        ? route('admin.resources.store', ['locale' => app()->getLocale(), 'resource' => $definition->key])
        : route('admin.resources.update', ['locale' => app()->getLocale(), 'resource' => $definition->key, 'id' => $record->getKey()]) }}">
        @csrf
        @unless ($isNew) @method('PUT') @endunless

        @if ($definition->fields !== [])
            <section class="a-panel">
                <div class="a-panel__head">
                    <h2>{{ __('admin.details') }}</h2>
                </div>

                <div class="a-form">
                    @foreach ($definition->fields as $field)
                        @php $current = old($field->name, $record->getAttribute($field->name)); @endphp

                        {{-- Shown, never written. "Superseded by" is the
                             reverse of a relation the later document owns:
                             a second writable control for the same fact is
                             how the two ends stop agreeing. --}}
                        @if ($field->readOnly)
                            @php $shown = ($field->optionsUsing)($record); @endphp

                            @if ($shown !== '')
                                <div class="a-field">
                                    <span class="a-label">{{ __('admin.field_'.$field->name) }}</span>
                                    <p class="a-readonly">{{ $shown }}</p>
                                    @if (__('admin.field_'.$field->name.'_hint') !== 'admin.field_'.$field->name.'_hint')
                                        <span class="a-hint">{{ __('admin.field_'.$field->name.'_hint') }}</span>
                                    @endif
                                </div>
                            @endif

                        @elseif ($field->control === 'checkbox')
                            <div class="a-field">
                                <label class="a-label" for="f-{{ $field->name }}">
                                    <input type="hidden" name="{{ $field->name }}" value="0">
                                    <input type="checkbox" id="f-{{ $field->name }}" name="{{ $field->name }}" value="1" @checked((bool) $current)>
                                    {{ __('admin.field_'.$field->name) }}
                                </label>
                                @if (__('admin.field_'.$field->name.'_hint') !== 'admin.field_'.$field->name.'_hint')
                                    <span class="a-hint">{{ __('admin.field_'.$field->name.'_hint') }}</span>
                                @endif
                                @error($field->name)<p class="c-field__error" id="e-{{ $field->name }}">{{ $message }}</p>@enderror
                            </div>

                        @elseif ($field->control === 'relation')
                            <div class="a-field">
                                <label class="a-label" for="f-{{ $field->name }}">{{ __('admin.field_'.$field->name) }}@if ($field->required) <span class="a-required">{{ __('admin.required') }}</span>@endif</label>
                                <select class="a-select" id="f-{{ $field->name }}" name="{{ $field->name }}" @required($field->required)>
                                    @unless ($field->required)<option value="">{{ __('admin.setting_none') }}</option>@endunless
                                    @foreach ($options[$field->name] ?? [] as $id => $label)
                                        <option value="{{ $id }}" @selected((string) $current === (string) $id)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @if (__('admin.field_'.$field->name.'_hint') !== 'admin.field_'.$field->name.'_hint')
                                    <span class="a-hint">{{ __('admin.field_'.$field->name.'_hint') }}</span>
                                @endif
                                @error($field->name)<p class="c-field__error">{{ $message }}</p>@enderror
                            </div>

                        @elseif ($field->control === 'select')
                            <div class="a-field">
                                <label class="a-label" for="f-{{ $field->name }}">{{ __('admin.field_'.$field->name) }}@if ($field->required) <span class="a-required">{{ __('admin.required') }}</span>@endif</label>
                                <select class="a-select" id="f-{{ $field->name }}" name="{{ $field->name }}" @required($field->required)>
                                    @unless ($field->required)<option value="">{{ __('admin.setting_none') }}</option>@endunless
                                    @foreach ($field->options as $option)
                                        <option value="{{ $option }}" @selected((string) ($current instanceof BackedEnum ? $current->value : $current) === (string) $option)>
                                            {{ __('admin.option_'.$option) === 'admin.option_'.$option ? $option : __('admin.option_'.$option) }}
                                        </option>
                                    @endforeach
                                </select>
                                @error($field->name)<p class="c-field__error" id="e-{{ $field->name }}">{{ $message }}</p>@enderror
                            </div>

                        @elseif ($field->control === 'textarea')
                            <div class="a-field">
                                <label class="a-label" for="f-{{ $field->name }}">{{ __('admin.field_'.$field->name) }}@if ($field->required) <span class="a-required">{{ __('admin.required') }}</span>@endif</label>
                                <textarea class="a-input" id="f-{{ $field->name }}" name="{{ $field->name }}" rows="4" @required($field->required)>{{ $current }}</textarea>
                                @error($field->name)<p class="c-field__error" id="e-{{ $field->name }}">{{ $message }}</p>@enderror
                            </div>

                        @else
                            <div class="a-field">
                                <label class="a-label" for="f-{{ $field->name }}">{{ __('admin.field_'.$field->name) }}@if ($field->required) <span class="a-required">{{ __('admin.required') }}</span>@endif</label>
                                <input class="a-input"
                                       type="{{ $field->control === 'date' ? 'date' : ($field->control === 'number' ? 'number' : ($field->control === 'url' ? 'url' : 'text')) }}"
                                       id="f-{{ $field->name }}" name="{{ $field->name }}"
                                       value="{{ $current instanceof \Illuminate\Support\Carbon ? $current->toDateString() : ($current instanceof BackedEnum ? $current->value : $current) }}"
                                       @required($field->required)>
                                @if (__('admin.field_'.$field->name.'_hint') !== 'admin.field_'.$field->name.'_hint')
                                    <span class="a-hint">{{ __('admin.field_'.$field->name.'_hint') }}</span>
                                @endif
                                @error($field->name)<p class="c-field__error" id="e-{{ $field->name }}">{{ $message }}</p>@enderror
                            </div>
                        @endif
                    @endforeach
                </div>
            </section>
        @endif

        @if ($definition->translatable !== [])
            <section class="a-panel">
                <div class="a-panel__head">
                    <h2>{{ __('admin.block_text') }}</h2>
                    <span class="a-panel__meta">{{ __('admin.page_fields_note') }}</span>
                </div>

                <div class="a-grid a-grid--medium">
                    @foreach ($locales as $locale)
                        <div class="a-form">
                            <h3 style="font-size:16px">{{ \App\Support\Locales::nameOf($locale) }}</h3>
                            @foreach ($definition->translatable as $field)
                                @php
                                    $value = $record->exists
                                        ? ($record->translationValuesFor($field)[$locale] ?? null)
                                        : null;
                                @endphp
                                <div class="a-field">
                                    <label class="a-label" for="t-{{ $locale }}-{{ $field }}">{{ __('admin.field_'.$field) }}</label>
                                    @if (in_array($field, ['body', 'summary', 'milestones'], true))
                                        <textarea class="a-input" id="t-{{ $locale }}-{{ $field }}" name="{{ $locale }}[{{ $field }}]" rows="{{ $field === 'body' ? 8 : ($field === 'milestones' ? 5 : 3) }}" lang="{{ $locale }}" @if ($field === 'milestones') aria-describedby="t-{{ $locale }}-milestones-help" @endif>{{ old($locale.'.'.$field, $value) }}</textarea>
                                        @if ($field === 'milestones')<span class="a-hint" id="t-{{ $locale }}-milestones-help">{{ __('admin.field_milestones_hint') }}</span>@endif
                                    @else
                                        <input class="a-input" type="text" id="t-{{ $locale }}-{{ $field }}" name="{{ $locale }}[{{ $field }}]" lang="{{ $locale }}" value="{{ old($locale.'.'.$field, $value) }}">
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <div class="a-form__actions">
            <button type="submit" class="a-btn a-btn--primary">
                {{ $isNew ? __('admin.resource_create', ['name' => $definition->singular()]) : __('admin.resource_save', ['name' => $definition->singular()]) }}
            </button>
            <a href="{{ route('admin.resources', ['locale' => app()->getLocale(), 'resource' => $definition->key]) }}">{{ __('admin.cancel') }}</a>
        </div>
    </form>

    @if (! $isNew && $definition->deletable)
        @can('delete', $record)
            <section class="a-panel" id="delete">
                <div class="a-panel__head">
                    <h2>{{ __('admin.delete_title') }}</h2>
                </div>

                <form class="a-form" method="POST"
                      action="{{ route('admin.resources.destroy', ['locale' => app()->getLocale(), 'resource' => $definition->key, 'id' => $record->getKey()]) }}"
                      data-confirm="{{ $definition->softDeletes() ? __('fa.delete_recoverable_confirm', ['name' => $definition->titleFor($record)]) : __('fa.delete_permanent_confirm', ['name' => $definition->titleFor($record)]) }}"
                      data-confirm-title="{{ __('fa.confirm_title') }}" data-confirm-action="{{ __('fa.delete') }}">
                    @csrf
                    @method('DELETE')

                    @if ($definition->softDeletes())
                        <p class="a-hint">{{ __('admin.delete_recoverable') }}</p>
                    @else
                        {{-- Not recoverable, so the confirmation is typed
                             rather than clicked past. The server checks the
                             typed name; it is not a client-side nicety. --}}
                        <div class="a-field">
                            <label class="a-label" for="confirm">{{ __('admin.delete_type_name', ['name' => $definition->titleFor($record)]) }}</label>
                            <input class="a-input" type="text" id="confirm" name="confirm" autocomplete="off">
                            <span class="a-hint">{{ __('admin.delete_permanent') }}</span>
                        </div>
                    @endif

                    <div class="a-form__actions">
                        <button type="submit" class="a-btn a-btn--danger">{{ __('admin.resource_delete', ['name' => $definition->singular()]) }}</button>
                    </div>
                </form>
            </section>
        @endcan
    @endif
@endsection

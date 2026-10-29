@extends('layouts.admin')

@section('title', $type->label())
@section('note', __('blocks.explain_'.$type->value))

@section('crumbs')
    <li><a href="{{ route('admin.pages', ['locale' => app()->getLocale()]) }}">{{ __('admin.section_content') }}</a></li>
    <li><a href="{{ route('admin.pages.edit', ['locale' => app()->getLocale(), 'page' => $page->slug]) }}">{{ $page->slug }}</a></li>
    <li aria-current="page">{{ $type->label() }}</li>
@endsection

@section('content')
    @php
        $value = fn (string $locale, string $field) => old(
            $locale.'.'.$field,
            $block->translations->firstWhere(fn ($t) => $t->locale === $locale && $t->field === $field)?->value
        );
        $settings = $block->settings ?? [];
        $long = ['body', 'intro', 'subhead'];
    @endphp

    <form method="POST" action="{{ route('admin.pages.blocks.update', ['locale' => app()->getLocale(), 'page' => $page->slug, 'block' => $block->id]) }}">
        @csrf
        @method('PUT')

        {{-- Text, one column per language. A block may carry English alone;
             requiring all three would block all publishing. --}}
        @if ($type->translatableFields() !== [])
            <section class="a-panel">
                <div class="a-panel__head">
                    <h2>{{ __('admin.block_text') }}</h2>
                    <span class="a-panel__meta">{{ __('admin.block_text_note') }}</span>
                </div>

                <div class="a-grid a-grid--medium">
                    @foreach ($locales as $locale)
                        <div class="a-form">
                            <h3 style="font-size:16px">{{ \App\Support\Locales::nameOf($locale) }}</h3>

                            @foreach ($type->translatableFields() as $field)
                                <div class="a-field">
                                    <label class="a-label" for="{{ $locale }}-{{ $field }}">{{ __('admin.field_'.$field) }}</label>
                                    @if (in_array($field, $long, true))
                                        {{-- A legal page is a document, not a caption. Six rows is right for an
                                             intro and unusable for a privacy notice, so the row
                                             count follows the field's own length cap. --}}
                                        <textarea class="a-input" id="{{ $locale }}-{{ $field }}" name="{{ $locale }}[{{ $field }}]" rows="{{ $type->maxLengthFor($field) > 4000 ? 24 : 6 }}" maxlength="{{ $type->maxLengthFor($field) }}">{{ $value($locale, $field) }}</textarea>
                                    @else
                                        <input class="a-input" type="text" id="{{ $locale }}-{{ $field }}" name="{{ $locale }}[{{ $field }}]" value="{{ $value($locale, $field) }}">
                                    @endif
                                </div>
                            @endforeach

                            {{-- Key figures: the figure is not per-language,
                                 its label and its source are. CLAUDE.md,
                                 multilingual rules. --}}
                            @if ($type === \App\Enums\BlockType::KeyFigures)
                                @foreach (($settings['items'] ?? []) as $index => $item)
                                    <div class="a-field">
                                        <label class="a-label" for="{{ $locale }}-item-{{ $index }}">{{ __('admin.field_item_label', ['n' => $index + 1]) }}</label>
                                        <input class="a-input" type="text" id="{{ $locale }}-item-{{ $index }}" name="{{ $locale }}[item_{{ $index }}_label]" value="{{ $value($locale, 'item_'.$index.'_label') }}">
                                    </div>
                                    {{-- The line of work the figure supports, on the homepage's
                                         "What we do" band (29 October 2026). --}}
                                    <div class="a-field">
                                        <label class="a-label" for="{{ $locale }}-item-{{ $index }}-heading">{{ __('admin.field_item_heading', ['n' => $index + 1]) }}</label>
                                        <input class="a-input" type="text" id="{{ $locale }}-item-{{ $index }}-heading" name="{{ $locale }}[item_{{ $index }}_heading]" value="{{ $value($locale, 'item_'.$index.'_heading') }}">
                                    </div>
                                    <div class="a-field">
                                        <label class="a-label" for="{{ $locale }}-item-{{ $index }}-body">{{ __('admin.field_item_body', ['n' => $index + 1]) }}</label>
                                        <textarea class="a-input" id="{{ $locale }}-item-{{ $index }}-body" name="{{ $locale }}[item_{{ $index }}_body]" rows="3" maxlength="4000">{{ $value($locale, 'item_'.$index.'_body') }}</textarea>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Settings. Every control here is generated from the block type's
             own validation rules, so a control and its rule cannot drift
             apart, and the server validates whatever comes back regardless
             of what the form offered. --}}
        @if ($type->settingsFields() !== [])
            <section class="a-panel">
                <div class="a-panel__head">
                    <h2>{{ __('admin.block_settings') }}</h2>
                    <span class="a-panel__meta">{{ __('admin.block_settings_note') }}</span>
                </div>

                <div class="a-form">
                    @foreach ($type->settingsFields() as $key => $field)
                        @php $current = $settings[$key] ?? null; @endphp

                        @if ($field['control'] === 'repeater')
                            <fieldset style="border:1px solid var(--a-rule);border-radius:var(--r-md);padding:var(--s-4)">
                                <legend class="a-label">{{ __('admin.setting_'.$key) }}</legend>
                                @php $rows = is_array($current) ? $current : []; @endphp
                                @for ($i = 0; $i <= count($rows); $i++)
                                    <div class="a-form__actions" style="align-items:flex-end;margin-bottom:var(--s-3)">
                                        @foreach ($field['fields'] as $sub => $subField)
                                            @php
                                                $row = $rows[$i] ?? null;
                                                $subValue = is_array($row) ? ($row[$sub] ?? null) : ($sub === 'value' ? $row : null);
                                                $name = 'settings['.$key.']['.$i.']'.($sub === 'value' && ! is_array($row) && $row !== null ? '' : '['.$sub.']');
                                                $name = count($field['fields']) === 1 && $sub === 'value'
                                                    ? 'settings['.$key.']['.$i.']'
                                                    : 'settings['.$key.']['.$i.']['.$sub.']';
                                            @endphp
                                            <div class="a-field" style="flex:1 1 160px">
                                                <label class="a-label" for="s-{{ $key }}-{{ $i }}-{{ $sub }}">{{ __('admin.setting_'.$sub) }} {{ $i + 1 }}</label>
                                                @if (($subField['control'] ?? 'text') === 'select')
                                                    <select class="a-select" id="s-{{ $key }}-{{ $i }}-{{ $sub }}" name="{{ $name }}">
                                                        <option value="">{{ __('admin.setting_none') }}</option>
                                                        @foreach ($subField['options'] as $option)
                                                            <option value="{{ $option }}" @selected($subValue === $option)>{{ $option }}</option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <input class="a-input" type="{{ $subField['control'] === 'number' ? 'number' : 'text' }}"
                                                           id="s-{{ $key }}-{{ $i }}-{{ $sub }}" name="{{ $name }}" value="{{ $subValue }}">
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endfor
                                <p class="a-hint">{{ __('admin.repeater_hint') }}</p>
                            </fieldset>

                        @elseif ($field['control'] === 'group')
                            <fieldset style="border:1px solid var(--a-rule);border-radius:var(--r-md);padding:var(--s-4)">
                                <legend class="a-label">{{ __('admin.setting_'.$key) }}</legend>
                                @foreach ($field['fields'] as $sub => $subField)
                                    <div class="a-field">
                                        <label class="a-label" for="s-{{ $key }}-{{ $sub }}">{{ __('admin.setting_'.$sub) }}</label>
                                        <input class="a-input" type="{{ $subField['control'] === 'number' ? 'number' : 'text' }}"
                                               id="s-{{ $key }}-{{ $sub }}" name="settings[{{ $key }}][{{ $sub }}]"
                                               value="{{ is_array($current) ? ($current[$sub] ?? '') : '' }}">
                                    </div>
                                @endforeach
                            </fieldset>

                        @elseif ($field['control'] === 'select')
                            <div class="a-field">
                                <label class="a-label" for="s-{{ $key }}">{{ __('admin.setting_'.$key) }}</label>
                                <select class="a-select" id="s-{{ $key }}" name="settings[{{ $key }}]">
                                    @foreach ($field['options'] as $option)
                                        <option value="{{ $option }}" @selected($current === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                            </div>

                        @elseif ($field['control'] === 'checkbox')
                            <div class="a-field">
                                <label class="a-label" for="s-{{ $key }}">
                                    <input type="hidden" name="settings[{{ $key }}]" value="0">
                                    <input type="checkbox" id="s-{{ $key }}" name="settings[{{ $key }}]" value="1" @checked((bool) $current)>
                                    {{ __('admin.setting_'.$key) }}
                                </label>
                            </div>

                        @else
                            <div class="a-field">
                                <label class="a-label" for="s-{{ $key }}">{{ __('admin.setting_'.$key) }}</label>
                                <input class="a-input" type="{{ $field['control'] === 'number' ? 'number' : 'text' }}"
                                       id="s-{{ $key }}" name="settings[{{ $key }}]" value="{{ is_array($current) ? '' : $current }}">
                            </div>
                        @endif
                    @endforeach
                </div>
            </section>
        @endif

        {{-- The placeholder flag. A block whose copy CRASA still has to
             supply says so on the page and appears in
             php artisan content:placeholders. --}}
        <section class="a-panel">
            <div class="a-panel__head">
                <h2>{{ __('admin.awaiting_content') }}</h2>
            </div>
            <div class="a-form">
                <div class="a-field">
                    <label class="a-label" for="needs_content">
                        <input type="hidden" name="needs_content" value="0">
                        <input type="checkbox" id="needs_content" name="needs_content" value="1" @checked(old('needs_content', $block->needs_content))>
                        {{ __('admin.needs_content_label') }}
                    </label>
                    <span class="a-hint">{{ __('admin.needs_content_hint') }}</span>
                </div>
                <div class="a-field">
                    <label class="a-label" for="content_note">{{ __('admin.content_note_label') }}</label>
                    <textarea class="a-input" id="content_note" name="content_note" rows="3" maxlength="500">{{ old('content_note', $block->content_note) }}</textarea>
                </div>
            </div>
        </section>

        <div class="a-form__actions">
            <button type="submit" class="a-btn a-btn--primary">{{ __('admin.save_block') }}</button>
            <a href="{{ route('admin.pages.edit', ['locale' => app()->getLocale(), 'page' => $page->slug]) }}">{{ __('admin.cancel') }}</a>
        </div>
    </form>
@endsection

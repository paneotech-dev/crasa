@extends('layouts.admin')

@section('title', $page->translate('title')?->value ?? $page->slug)
@section('note', __('admin.page_edit_note', ['slug' => '/'.$page->slug]))

@section('crumbs')
    <li><a href="{{ route('admin.pages', ['locale' => app()->getLocale()]) }}">{{ __('admin.section_content') }}</a></li>
    <li aria-current="page">{{ $page->slug }}</li>
@endsection

@section('actions')
    <a class="a-btn a-btn--secondary" href="{{ route('admin.pages.preview', ['locale' => app()->getLocale(), 'page' => $page->slug]) }}">{{ __('admin.preview') }}</a>
    @if ($page->isPublished())
        <a class="a-btn a-btn--secondary" href="{{ \App\Support\Navigation::url($page->slug) }}">{{ __('admin.view_live') }}</a>
    @endif
@endsection

@section('content')

    {{-- Workflow. The buttons are only what this account may actually do:
         the controller re-checks both the transition and the permission,
         so a missing button here grants nobody anything. --}}
    <section class="a-panel">
        <div class="a-panel__head">
            <h2>{{ __('admin.workflow') }}</h2>
            <span class="a-panel__meta">
                <span class="a-pill" data-status="{{ $page->status->value }}">{{ __($page->status->labelKey()) }}</span>
            </span>
        </div>

        @if ($transitions === [])
            <div class="a-empty">
                <div class="a-empty__title">{{ __('admin.workflow_locked_title') }}</div>
                <div class="a-empty__body">{{ __('admin.workflow_locked_body') }}</div>
            </div>
        @else
            <div class="a-form__actions">
                @foreach ($transitions as $target)
                    <form method="POST" action="{{ route('admin.pages.status', ['locale' => app()->getLocale(), 'page' => $page->slug]) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="status" value="{{ $target->value }}">
                        <button type="submit" class="a-btn {{ $target === \App\Enums\PageStatus::Published ? 'a-btn--primary' : 'a-btn--secondary' }}">
                            {{ __($target->labelKey().'_action') }}
                        </button>
                    </form>
                @endforeach
            </div>
        @endif
    </section>

    {{-- The page's own fields, one column per language. Screen 05 draws
         translation side by side; this is the same idea at page scale. --}}
    <section class="a-panel">
        <div class="a-panel__head">
            <h2>{{ __('admin.page_fields') }}</h2>
            <span class="a-panel__meta">{{ __('admin.page_fields_note') }}</span>
        </div>

        <form method="POST" action="{{ route('admin.pages.update', ['locale' => app()->getLocale(), 'page' => $page->slug]) }}">
            @csrf
            @method('PUT')

            <div class="a-grid a-grid--medium">
                @foreach ($locales as $locale)
                    <div class="a-form">
                        <h3 style="font-size:16px">{{ \App\Support\Locales::nameOf($locale) }}</h3>

                        @foreach (['title' => 255, 'meta_title' => 255, 'meta_description' => 320] as $field => $max)
                            <div class="a-field">
                                <label class="a-label" for="{{ $locale }}-{{ $field }}">{{ __('admin.field_'.$field) }}</label>
                                @if ($field === 'meta_description')
                                    <textarea class="a-input" id="{{ $locale }}-{{ $field }}" name="{{ $locale }}[{{ $field }}]" rows="3" maxlength="{{ $max }}">{{ old($locale.'.'.$field, $page->translations->firstWhere(fn ($t) => $t->locale === $locale && $t->field === $field)?->value) }}</textarea>
                                @else
                                    <input class="a-input" type="text" id="{{ $locale }}-{{ $field }}" name="{{ $locale }}[{{ $field }}]" maxlength="{{ $max }}"
                                           value="{{ old($locale.'.'.$field, $page->translations->firstWhere(fn ($t) => $t->locale === $locale && $t->field === $field)?->value) }}">
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>

            <div class="a-form__actions" style="margin-top:var(--s-5)">
                <button type="submit" class="a-btn a-btn--primary">{{ __('admin.save_page') }}</button>
            </div>
        </form>
    </section>

    {{-- The blocks. Screen 14 draws content as rows in a panel, so these
         are rows in a panel. Reordering is two buttons, which is also the
         only version that works from a keyboard without a bespoke widget. --}}
    <section class="a-resource">
        <div class="a-resource__head">
            <div class="a-resource__bar">
                <div>
                    <h2 class="a-resource__title">{{ __('admin.blocks') }}</h2>
                    <div class="a-resource__note">{{ __('admin.blocks_note') }}</div>
                </div>
                <div class="a-pagehead__actions">
                    <a class="a-btn a-btn--primary" href="{{ route('admin.pages.blocks.create', ['locale' => app()->getLocale(), 'page' => $page->slug]) }}">{{ __('admin.add_block') }}</a>
                </div>
            </div>
        </div>

        @if ($page->editableBlocks->isEmpty())
            <div style="padding:var(--s-5)">
                <div class="a-empty">
                    <div class="a-empty__title">{{ __('admin.blocks_empty_title') }}</div>
                    <div class="a-empty__body">{{ __('admin.blocks_empty_body') }}</div>
                </div>
            </div>
        @else
            @foreach ($page->editableBlocks as $index => $block)
                @php
                    $blockLocales = $block->translations->pluck('locale')->unique()->values()->all();
                    $heading = $block->translate('heading') ?? $block->translate('headline');
                @endphp
                <div class="a-crow" @if ($block->needs_content) data-incomplete @endif>
                    <div class="a-crow__main">
                        <div class="a-crow__field">{{ $block->type()->label() }}</div>
                        <div class="a-crow__value">
                            {{ $heading?->value ?: __('admin.block_untitled') }}
                        </div>
                        @if ($block->needs_content && $block->content_note)
                            <div class="a-crow__where" style="margin-top:6px">{{ $block->content_note }}</div>
                        @endif
                    </div>

                    <div class="a-crow__aside">
                        <span class="a-crow__where">{{ __('admin.block_position', ['n' => $index + 1, 'of' => $page->editableBlocks->count()]) }}</span>
                        <span class="a-langs" @if (count($blockLocales) < 3) data-incomplete @endif>
                            {{ $blockLocales === [] ? __('admin.no_text') : implode(' ', array_map('strtoupper', $blockLocales)) }}
                        </span>
                        <span>
                            <span class="a-pill" data-status="{{ $block->is_visible ? 'published' : 'draft' }}">
                                {{ $block->is_visible ? __('admin.visible') : __('admin.hidden') }}
                            </span>
                        </span>
                    </div>

                    <div class="a-crow__actions">
                        <a href="{{ route('admin.pages.blocks.edit', ['locale' => app()->getLocale(), 'page' => $page->slug, 'block' => $block->id]) }}">{{ __('admin.edit') }}</a>

                        <form method="POST" action="{{ route('admin.pages.blocks.move', ['locale' => app()->getLocale(), 'page' => $page->slug, 'block' => $block->id]) }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="direction" value="up">
                            <button type="submit" class="a-linkbutton" @disabled($loop->first)>{{ __('admin.move_up') }}</button>
                        </form>

                        <form method="POST" action="{{ route('admin.pages.blocks.move', ['locale' => app()->getLocale(), 'page' => $page->slug, 'block' => $block->id]) }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="direction" value="down">
                            <button type="submit" class="a-linkbutton" @disabled($loop->last)>{{ __('admin.move_down') }}</button>
                        </form>

                        <form method="POST" action="{{ route('admin.pages.blocks.visibility', ['locale' => app()->getLocale(), 'page' => $page->slug, 'block' => $block->id]) }}">
                            @csrf @method('PUT')
                            <button type="submit" class="a-linkbutton">{{ $block->is_visible ? __('admin.hide') : __('admin.show') }}</button>
                        </form>

                        <form method="POST" action="{{ route('admin.pages.blocks.duplicate', ['locale' => app()->getLocale(), 'page' => $page->slug, 'block' => $block->id]) }}">
                            @csrf
                            <button type="submit" class="a-linkbutton">{{ __('admin.duplicate') }}</button>
                        </form>

                        @can('delete', $block)
                            <form method="POST"
                                  action="{{ route('admin.pages.blocks.destroy', ['locale' => app()->getLocale(), 'page' => $page->slug, 'block' => $block->id]) }}"
                                  onsubmit="return confirm('{{ __('admin.block_delete_confirm', ['type' => $block->type()->label()]) }}')">
                                @csrf @method('DELETE')
                                <button type="submit" class="a-linkbutton a-linkbutton--danger">{{ __('admin.delete') }}</button>
                            </form>
                        @endcan
                    </div>
                </div>
            @endforeach
        @endif
    </section>

    {{-- CLIENT REVIEW. Administration only.

         Until 9 September 2026 the privacy notice and the terms of use
         announced their own draft status in their opening paragraph, so
         every reader of a public legal page met the project's internal
         state before meeting the text. The announcement moved here. The
         text stays exactly as drafted, blanks and all, and the list below
         is read out of the text rather than kept alongside it: fill a blank
         in and it leaves the list. --}}
    @if ($page->needs_client_review || $page->client_reviewed_at)
        <section class="a-panel">
            <div class="a-panel__head">
                <h2>{{ __('admin.client_review') }}</h2>
                <span class="a-panel__meta">{{ __('admin.client_review_note') }}</span>
            </div>

            @if ($page->awaitsClientReview())
                <p class="a-hint">{{ __('admin.client_review_body') }}</p>

                @if ($outstanding === [])
                    <p class="a-hint">{{ __('admin.client_review_none') }}</p>
                @else
                    <ul class="a-review">
                        @foreach ($outstanding as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                @endif
            @else
                <p class="a-hint">{{ __('admin.client_review_cleared_by', [
                    'name' => $page->clientReviewer?->name ?? __('admin.page_history_system'),
                    'date' => $page->client_reviewed_at?->isoFormat('D MMMM YYYY') ?? '',
                ]) }}</p>
            @endif

            @can('clientReview', $page)
                <form class="a-form__actions" method="POST" action="{{ route('admin.pages.client-review', ['locale' => app()->getLocale(), 'page' => $page->slug]) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="reviewed" value="{{ $page->awaitsClientReview() ? 1 : 0 }}">
                    <button type="submit" class="a-btn {{ $page->awaitsClientReview() ? 'a-btn--primary' : 'a-btn--secondary' }}">
                        {{ __($page->awaitsClientReview() ? 'admin.client_review_mark' : 'admin.client_review_reopen') }}
                    </button>
                </form>
            @endcan
        </section>
    @endif

    {{-- HISTORY. A version records what the page said, which is the question
         the audit log cannot answer. --}}
    <section class="a-panel">
        <div class="a-panel__head">
            <h2>{{ __('admin.page_history') }}</h2>
            <span class="a-panel__meta">{{ __('admin.page_history_note') }}</span>
        </div>

        @if ($versions->isEmpty())
            <div class="a-empty">
                <div class="a-empty__body">{{ __('admin.page_history_empty') }}</div>
            </div>
        @else
            <ol class="a-history">
                @foreach ($versions as $version)
                    <li>
                        <span class="a-history__n">{{ __('admin.page_history_version', ['n' => $version->version_number]) }}</span>
                        <span class="a-history__what">{{ $version->change_summary }}</span>
                        <span class="a-history__who">
                            {{ $version->created_at?->isoFormat('D MMM YYYY, HH:mm') }}
                            {{ $version->author
                                ? __('admin.page_history_by', ['name' => $version->author->name])
                                : __('admin.page_history_system') }}
                        </span>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>
@endsection
@extends('layouts.admin')

@section('title', __('admin.preview_title', ['language' => \App\Support\Locales::nameOf($previewLocale)]))
@section('note', $label)

@section('crumbs')
    <li><a href="{{ route('admin.translations', ['locale' => app()->getLocale(), 'type' => $type]) }}">{{ __('admin.translations_title') }}</a></li>
    <li><a href="{{ route('admin.translations.edit', ['locale' => app()->getLocale(), 'type' => $type, 'id' => $record->getKey()]) }}">{{ $label }}</a></li>
    <li aria-current="page">{{ \App\Support\Locales::nameOf($previewLocale) }}</li>
@endsection

@section('content')
    {{--
        Screen 05: "The same publication rendered on the public site with the
        interface in Portuguese. The summary has no Portuguese translation,
        so the English text is shown, marked as English with a lang attribute
        so a screen reader switches voice, and preceded by a plain notice. It
        is never left blank, and never machine translated on the fly."

        This runs the real resolver rather than describing it, so what an
        editor sees here is what a reader gets, including the fallback and
        the notice above it.
    --}}
    <section class="a-panel">
        <div class="a-panel__head">
            <h2>{{ __('admin.preview_as_reader') }}</h2>
            <span class="a-panel__meta">{{ __('admin.preview_note') }}</span>
        </div>

        <div class="a-rows">
            @foreach ($fields as $field)
                @php $text = $resolved[$field]; @endphp
                <div class="a-row" style="align-items:flex-start;flex-direction:column;gap:6px">
                    <span class="a-crow__field">{{ __('admin.field_'.$field) }}</span>

                    @if ($text === null)
                        <div class="a-empty" style="width:100%">
                            <div class="a-empty__title">{{ __('admin.preview_empty_title') }}</div>
                            <div class="a-empty__body">{{ __('admin.preview_empty_body') }}</div>
                        </div>
                    @else
                        @if ($text->isFallback())
                            {{-- The notice a reader actually sees, in the
                                 language they are reading. --}}
                            <div class="a-notice a-notice--pending" role="note" style="width:100%">
                                <strong>{{ __('site.fallback_title', ['language' => __('site.languages.'.$previewLocale, [], $previewLocale)], $previewLocale) }}</strong>
                                {{ __('site.fallback_body', ['language' => __('site.languages.'.$previewLocale, [], $previewLocale)], $previewLocale) }}
                            </div>
                        @endif
                        <div class="a-crow__value" @if ($text->isFallback()) lang="{{ $text->locale }}" @else lang="{{ $previewLocale }}" @endif>
                            {{ $text->value }}
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    <div class="a-form__actions">
        <a class="a-btn a-btn--secondary" href="{{ route('admin.translations.edit', ['locale' => app()->getLocale(), 'type' => $type, 'id' => $record->getKey()]) }}">{{ __('admin.back_to_editor') }}</a>
    </div>
@endsection

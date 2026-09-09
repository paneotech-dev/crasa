@extends('layouts.admin')

@section('title', __('admin.translations_title'))
@section('note', $label)

@section('crumbs')
    <li><a href="{{ route('admin.translations', ['locale' => app()->getLocale(), 'type' => $type]) }}">{{ __('admin.translations_title') }}</a></li>
    <li aria-current="page">{{ $label }}</li>
@endsection

@section('content')
    @php
        $url = fn (?string $pairCode) => route('admin.translations.edit', array_filter([
            'locale' => app()->getLocale(), 'type' => $type, 'id' => $record->getKey(), 'pair' => $pairCode,
        ]));
        $notReady = collect($coverage)->reject(fn ($c) => $c['ready']);
    @endphp

    {{-- Screen 05's three views: all three languages, or English against
         one. Links rather than buttons, because each is a different view of
         the same record and should be reachable and shareable. --}}
    <section class="a-panel">
        <div class="a-form__actions">
            <div class="a-toggle" role="group" aria-label="{{ __('admin.translation_view') }}">
                <a href="{{ $url(null) }}" aria-current="{{ $pair === '' ? 'true' : 'false' }}">{{ __('admin.view_side_by_side') }}</a>
                <a href="{{ $url('fr') }}" aria-current="{{ $pair === 'fr' ? 'true' : 'false' }}">{{ __('admin.view_pair', ['code' => 'FR']) }}</a>
                <a href="{{ $url('pt') }}" aria-current="{{ $pair === 'pt' ? 'true' : 'false' }}">{{ __('admin.view_pair', ['code' => 'PT']) }}</a>
            </div>

            <div style="margin-left:auto;display:flex;gap:var(--s-5);flex-wrap:wrap">
                @foreach (\App\Support\Locales::SUPPORTED as $locale)
                    @continue($locale === \App\Support\Locales::FALLBACK)
                    @php $counts = $coverage[$locale]; @endphp
                    <span class="a-coverage" @if ($counts['ready']) data-ready @endif>
                        <span class="a-coverage__code">{{ strtoupper(\App\Support\Locales::short($locale)) }}</span>
                        <span class="a-coverage__track">
                            <span class="a-coverage__fill" style="width:{{ \App\Support\TranslationCoverage::percentage($counts) }}%"></span>
                        </span>
                        <span class="a-coverage__note">{{ \App\Support\TranslationCoverage::describe($counts) }}</span>
                    </span>
                @endforeach
            </div>
        </div>
    </section>

    @foreach ($notReady as $locale => $counts)
        @continue($locale === \App\Support\Locales::FALLBACK)
        <div class="a-band">
            <strong>{{ __('admin.not_ready_title', ['language' => \App\Support\Locales::nameOf($locale)]) }}</strong>
            {{ __('admin.not_ready_body') }}
        </div>
    @endforeach

    <form method="POST" action="{{ route('admin.translations.update', ['locale' => app()->getLocale(), 'type' => $type, 'id' => $record->getKey()]) }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="pair" value="{{ $pair }}">

        <section class="a-resource">
            <div class="a-tgrid a-tgrid__head" style="--a-cols:{{ count($columns) }}">
                <div class="a-tgrid__headcell"><b>{{ __('admin.col_field') }}</b></div>
                @foreach ($columns as $locale)
                    <div class="a-tgrid__headcell">
                        <b>{{ \App\Support\Locales::nameOf($locale) }}</b>
                        <span>{{ $locale === \App\Support\Locales::FALLBACK ? __('admin.source_language') : \App\Support\TranslationCoverage::describe($coverage[$locale]) }}</span>
                    </div>
                @endforeach
            </div>

            @foreach ($fields as $field)
                <div class="a-tgrid a-tgrid__row" style="--a-cols:{{ count($columns) }}">
                    <div class="a-tgrid__field">
                        <b>{{ __('admin.field_'.$field) }}</b>
                        <span>{{ __('admin.fieldhint_'.$field) === 'admin.fieldhint_'.$field ? '' : __('admin.fieldhint_'.$field) }}</span>
                    </div>

                    @foreach ($columns as $locale)
                        @php
                            $state = \App\Support\TranslationCoverage::state($record, $field, $locale);
                            $translation = $record->translations->firstWhere(fn ($t) => $t->locale === $locale && $t->field === $field);
                        @endphp
                        <div class="a-tgrid__cell">
                            <div class="a-tgrid__meta">
                                <span class="a-tpill" data-state="{{ $state }}">{{ __('admin.tstate_'.$state) }}</span>
                                @if ($translation?->updated_at)
                                    <span>{{ $translation->updated_at->isoFormat('D MMM YYYY') }}</span>
                                @endif
                            </div>

                            <label class="c-sr" for="{{ $locale }}-{{ $field }}">
                                {{ __('admin.field_in', ['field' => __('admin.field_'.$field), 'language' => \App\Support\Locales::nameOf($locale)]) }}
                            </label>
                            <textarea class="a-tbox" data-state="{{ $state }}" lang="{{ $locale }}"
                                      id="{{ $locale }}-{{ $field }}" name="{{ $locale }}[{{ $field }}]"
                                      rows="{{ in_array($field, ['body', 'summary', 'intro', 'subhead'], true) ? 6 : 2 }}">{{ old($locale.'.'.$field, $translation?->value) }}</textarea>

                            @if ($state === 'untranslated' && $locale !== \App\Support\Locales::FALLBACK)
                                <div class="a-fallback">
                                    <div>
                                        <strong>{{ __('admin.no_translation', ['language' => \App\Support\Locales::nameOf($locale)]) }}</strong>
                                        {{ __('admin.fallback_explained', ['language' => \App\Support\Locales::nameOf($locale)]) }}
                                    </div>
                                </div>
                            @endif

                            @if ($state === 'machine')
                                <div class="a-draft">
                                    <p>{{ __('admin.machine_draft_explained', ['language' => \App\Support\Locales::nameOf($locale)]) }}</p>
                                    {{-- Approve is a separate form: it
                                         records that a speaker of the
                                         language stands behind the string as
                                         it is, and rewrites nothing. --}}
                                    <button type="submit" class="a-btn a-btn--secondary"
                                            formaction="{{ route('admin.translations.approve', ['locale' => app()->getLocale(), 'type' => $type, 'id' => $record->getKey(), 'field' => $field, 'approve_locale' => $locale]) }}"
                                            formmethod="POST">{{ __('admin.approve') }}</button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endforeach
        </section>

        <div class="a-form__actions">
            <button type="submit" class="a-btn a-btn--primary">{{ __('admin.save_translations') }}</button>
            @foreach (\App\Support\Locales::SUPPORTED as $locale)
                <a href="{{ route('admin.translations.preview', ['locale' => app()->getLocale(), 'type' => $type, 'id' => $record->getKey(), 'previewLocale' => $locale]) }}">
                    {{ __('admin.preview_in', ['language' => \App\Support\Locales::nameOf($locale)]) }}
                </a>
            @endforeach
        </div>
    </form>
@endsection

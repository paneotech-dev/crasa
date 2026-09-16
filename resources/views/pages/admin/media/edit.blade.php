@extends('layouts.admin')

@section('title', __('admin.media_detail'))
@section('note', $asset->original_name)

@section('crumbs')
    <li><a href="{{ route('admin.media', ['locale' => app()->getLocale()]) }}">{{ __('admin.media_title') }}</a></li>
    <li aria-current="page">{{ $asset->original_name }}</li>
@endsection

@section('content')
    @if (! $asset->hasCompleteAlt())
        <div class="a-band a-band--error">
            <strong>{{ __('admin.media_blocked_title') }}</strong>
            {{ __('admin.media_blocked_body', [
                'languages' => implode(', ', array_map(
                    fn ($l) => \App\Support\Locales::nameOf($l),
                    array_values(array_diff(\App\Support\Locales::SUPPORTED, $asset->localesWithAlt()))
                )),
            ]) }}
        </div>
    @endif

    <section class="a-panel">
        <div class="a-split">
            {{-- Left: the image and the renditions actually on disk. Screen
                 14 draws the rendition table as a static rendition; these
                 sizes are read back from the files. --}}
            <div class="a-split__main">
                <div class="a-preview">
                    @if ($asset->url(800))
                        <img src="{{ $asset->url(800) }}" alt="" width="{{ $asset->width }}" height="{{ $asset->height }}">
                    @endif
                </div>

                <h3 style="font-size:14px;margin-top:var(--s-4)">{{ __('admin.renditions') }}</h3>
                <table class="a-table" style="min-width:0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('admin.rendition_width') }}</th>
                            <th scope="col">{{ __('admin.rendition_use') }}</th>
                            <th scope="col" data-numeric>{{ __('admin.rendition_size') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (($asset->renditions ?? []) as $width => $rendition)
                            <tr>
                                <th scope="row">{{ $width }}px</th>
                                <td>{{ __('admin.rendition_use_'.$width, [], null) === 'admin.rendition_use_'.$width ? __('admin.rendition_use_other') : __('admin.rendition_use_'.$width) }}</td>
                                <td data-numeric>{{ strtoupper($rendition['format']) }} {{ max(1, (int) round($rendition['bytes'] / 1024)) }} KB</td>
                            </tr>
                        @empty
                            <tr><td colspan="3">{{ __('admin.renditions_none') }}</td></tr>
                        @endforelse
                        <tr>
                            <th scope="row">{{ __('admin.rendition_original') }}</th>
                            <td>{{ __('admin.rendition_use_original') }}</td>
                            <td data-numeric>{{ $asset->humanSize() }}</td>
                        </tr>
                    </tbody>
                </table>
                <p class="a-hint">{{ __('admin.renditions_note') }}</p>
            </div>

            {{-- Right: alt text per language, caption, rights. Screen 14
                 marks all three alt fields required and gives each a
                 different state. --}}
            <div class="a-split__side">
                <form class="a-form" method="POST" action="{{ route('admin.media.update', ['locale' => app()->getLocale(), 'asset' => $asset->id]) }}">
                    @csrf
                    @method('PUT')

                    @foreach ($locales as $locale)
                        @php
                            $alt = $asset->translationValuesFor('alt')[$locale] ?? null;
                            $caption = $asset->translationValuesFor('caption')[$locale] ?? null;
                        @endphp
                        <div class="a-field" data-state="{{ trim((string) $alt) === '' ? 'missing' : 'complete' }}">
                            <label class="a-label" for="alt-{{ $locale }}">
                                {{ __('admin.alt_for', ['language' => \App\Support\Locales::nameOf($locale)]) }}
                                <span class="a-required" aria-hidden="true">*</span>
                                <span class="c-sr">{{ __('admin.required') }}</span>
                            </label>
                            <input class="a-input" type="text" id="alt-{{ $locale }}" name="{{ $locale }}[alt]" maxlength="255"
                                   lang="{{ $locale }}" value="{{ old($locale.'.alt', $alt) }}">
                            <span class="a-hint">
                                {{ trim((string) $alt) === '' ? __('admin.alt_required_hint', ['language' => \App\Support\Locales::nameOf($locale)]) : __('admin.alt_hint') }}
                            </span>
                        </div>
                    @endforeach

                    @foreach ($locales as $locale)
                        <div class="a-field">
                            <label class="a-label" for="caption-{{ $locale }}">{{ __('admin.caption_for', ['language' => \App\Support\Locales::nameOf($locale)]) }}</label>
                            <input class="a-input" type="text" id="caption-{{ $locale }}" name="{{ $locale }}[caption]" maxlength="500"
                                   lang="{{ $locale }}" value="{{ old($locale.'.caption', $asset->translationValuesFor('caption')[$locale] ?? null) }}">
                        </div>
                    @endforeach

                    <fieldset style="border:1px solid var(--a-rule);border-radius:var(--r-md);padding:var(--s-4)">
                        <legend class="a-label">{{ __('admin.rights') }}</legend>

                        <div class="a-field">
                            <label class="a-label" for="rights_holder">{{ __('admin.rights_holder') }}</label>
                            <input class="a-input" type="text" id="rights_holder" name="rights_holder" value="{{ old('rights_holder', $asset->rights_holder) }}">
                        </div>
                        <div class="a-field">
                            <label class="a-label" for="licence">{{ __('admin.licence') }}</label>
                            <input class="a-input" type="text" id="licence" name="licence" value="{{ old('licence', $asset->licence) }}">
                        </div>
                        <div class="a-field">
                            <label class="a-label" for="source_url">{{ __('admin.source_url') }}</label>
                            <input class="a-input" type="url" id="source_url" name="source_url" value="{{ old('source_url', $asset->source_url) }}">
                        </div>
                        <div class="a-field">
                            <label class="a-label" for="rights_confirmed">
                                <input type="hidden" name="rights_confirmed" value="0">
                                <input type="checkbox" id="rights_confirmed" name="rights_confirmed" value="1" @checked(old('rights_confirmed', $asset->rights_confirmed_at !== null))>
                                {{ __('admin.rights_confirmed') }}
                            </label>
                            @if ($asset->rights_confirmed_at)
                                <span class="a-hint">{{ __('admin.rights_confirmed_on', [
                                    'date' => $asset->rights_confirmed_at->isoFormat('D MMMM YYYY'),
                                    'who' => $asset->rightsConfirmer?->name ?? __('admin.system'),
                                ]) }}</span>
                            @endif
                        </div>
                    </fieldset>

                    <div class="a-form__actions">
                        <button type="submit" class="a-btn a-btn--primary">{{ __('admin.save_asset') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <section class="a-panel">
        <div class="a-panel__head">
            <h2>{{ __('admin.media_used_on_title') }}</h2>
        </div>

        @if ($usage === [])
            <div class="a-empty">
                <div class="a-empty__title">{{ __('admin.media_unused') }}</div>
                <div class="a-empty__body">{{ __('admin.media_unused_body') }}</div>
            </div>
        @else
            <div class="a-rows">
                @foreach ($usage as $use)
                    <div class="a-row">
                        <span class="a-row__grow">
                            @if ($use['url'])<a href="{{ $use['url'] }}">{{ $use['label'] }}</a>@else{{ $use['label'] }}@endif
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <section class="a-panel">
        <div class="a-panel__head">
            <h2>{{ __('admin.media_file') }}</h2>
        </div>

        <div class="a-split">
            <form class="a-form a-split__main" method="POST" enctype="multipart/form-data"
                  action="{{ route('admin.media.replace', ['locale' => app()->getLocale(), 'asset' => $asset->id]) }}">
                @csrf
                <div class="a-field">
                    <label class="a-label" for="replacement">{{ __('admin.replace_file') }}</label>
                    <input class="a-input" type="file" id="replacement" name="file" required
                           accept="{{ implode(',', \App\Services\MediaLibrary::ACCEPTED) }}">
                    <span class="a-hint">{{ __('admin.replace_hint') }}</span>
                </div>
                <div class="a-form__actions">
                    <button type="submit" class="a-btn a-btn--secondary">{{ __('admin.replace_submit') }}</button>
                </div>
            </form>

            @can('delete', $asset)
                <form class="a-form a-split__side" method="POST"
                      action="{{ route('admin.media.destroy', ['locale' => app()->getLocale(), 'asset' => $asset->id]) }}"
                      onsubmit="return confirm('{{ __('admin.media_delete_confirm', ['name' => $asset->original_name]) }}')">
                    @csrf
                    @method('DELETE')
                    <p class="a-hint">{{ $usage === [] ? __('admin.media_delete_hint') : __('admin.media_delete_blocked', ['n' => count($usage)]) }}</p>
                    <div class="a-form__actions">
                        <button type="submit" class="a-btn a-btn--danger" @disabled($usage !== [])>{{ __('admin.media_delete') }}</button>
                    </div>
                </form>
            @endcan
        </div>
    </section>
@endsection

{{--
    The news item's photograph (Day 3, taxo).

    Two ways in, side by side: choose a photograph already in the media
    library, or upload one here. An upload goes through the media library's
    own store, so it is deduplicated, stripped of EXIF and given the same
    WebP renditions as one uploaded on the media screen, and it is refused
    without alt text in all three languages.

    The focal point is the point the photograph is cropped around on the
    public card and the article hero (CSS object-position). Click the
    thumbnail, move the marker with the arrow keys, or type the two
    percentages. Empty keeps the framing the site used before.
--}}
@php
    $focal = $field->options;
    $current = old($field->name, $record->getAttribute($field->name));
    $choices = $options[$field->name] ?? [];
    $fx = old($focal['x'], $record->getAttribute($focal['x']));
    $fy = old($focal['y'], $record->getAttribute($focal['y']));
    $thumb = fn (?string $path) => $path ? \App\Support\ResizedImage::attributes($path, 'card')['src'] : '';
@endphp
@push('scripts')
    <script src="{{ \App\Support\Assets::url('js/news-photo.js') }}" defer></script>
@endpush

<fieldset class="a-field nf-photo" data-news-photo>
    <legend class="a-label">{{ __('d3_taxo.photo') }}</legend>

    {{-- Item 1i: the photograph in use, or the one just uploaded, is smaller
         than a news card and article hero need. --}}
    @php
        $currentAsset = $current ? \App\Models\MediaAsset::where('path', $current)->first() : null;
        $photoWarnings = array_unique(array_filter([session('m3_media_warning'), $currentAsset ? \App\Support\ImageSlots::warning($currentAsset, 'news') : null]));
    @endphp
    @foreach ($photoWarnings as $photoWarning)
        <p class="a-band" role="status" data-size-warning>{{ $photoWarning }}</p>
    @endforeach

    <div class="nf-photo__ways">
        <div class="a-field">
            <label class="a-label" for="f-{{ $field->name }}">{{ __('d3_taxo.photo_choose') }}</label>
            <select class="a-select" id="f-{{ $field->name }}" name="{{ $field->name }}" data-photo-select>
                <option value="" data-thumb="">{{ __('d3_taxo.photo_none') }}</option>
                @foreach ($choices as $path => $label)
                    <option value="{{ $path }}" data-thumb="{{ $thumb($path) }}" @selected((string) $current === (string) $path)>{{ $label }}</option>
                @endforeach
            </select>
            <span class="a-hint">{{ __('admin.field_hero_image_path_hint') }}</span>
            @error($field->name)<p class="c-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="a-field">
            <label class="a-label" for="photo_upload">{{ __('d3_taxo.photo_upload') }}</label>
            <input class="a-input" type="file" id="photo_upload" name="photo_upload" data-photo-upload
                   accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" aria-describedby="photo-upload-help">
            <span class="a-hint" id="photo-upload-help">{{ __('d3_taxo.photo_upload_help') }}</span>
            @error('photo_upload')<p class="c-field__error">{{ $message }}</p>@enderror
            @include('pages.admin.media._slot', ['fileInput' => 'photo_upload', 'slotName' => 'photo_used_for', 'selected' => 'news'])
            @foreach (\App\Support\Locales::SUPPORTED as $locale)
                <label class="a-label" for="photo-alt-{{ $locale }}">{{ __('d3_taxo.photo_alt_in', ['language' => \App\Support\Locales::nameOf($locale)]) }}</label>
                <input class="a-input" type="text" id="photo-alt-{{ $locale }}" name="photo_alt[{{ $locale }}]" lang="{{ $locale }}" maxlength="255"
                       value="{{ old('photo_alt.'.$locale) }}" data-photo-alt>
                @error('photo_alt.'.$locale)<p class="c-field__error">{{ $message }}</p>@enderror
            @endforeach
        </div>
    </div>

    <div class="nf-photo__focal">
        <div class="nf-photo__frame" data-photo-frame @if (! $current) hidden @endif>
            <img src="{{ $thumb($current) }}" alt="" data-photo-thumb>
            <button type="button" class="nf-photo__marker" data-photo-marker
                    aria-label="{{ __('d3_taxo.focal_marker') }}" aria-describedby="focal-help"
                    style="left: {{ $fx ?? 50 }}%; top: {{ $fy ?? 50 }}%"></button>
        </div>
        <fieldset class="nf-photo__point">
            <legend class="a-label">{{ __('d3_taxo.focal') }}</legend>
            <p class="a-hint" id="focal-help">{{ __('d3_taxo.focal_help') }}</p>
            <label class="a-label" for="f-{{ $focal['x'] }}">{{ __('d3_taxo.focal_x') }}</label>
            <input class="a-input" type="number" min="0" max="100" step="1" inputmode="numeric" id="f-{{ $focal['x'] }}" name="{{ $focal['x'] }}" value="{{ $fx }}" data-focal-x>
            @error($focal['x'])<p class="c-field__error">{{ $message }}</p>@enderror
            <label class="a-label" for="f-{{ $focal['y'] }}">{{ __('d3_taxo.focal_y') }}</label>
            <input class="a-input" type="number" min="0" max="100" step="1" inputmode="numeric" id="f-{{ $focal['y'] }}" name="{{ $focal['y'] }}" value="{{ $fy }}" data-focal-y>
            @error($focal['y'])<p class="c-field__error">{{ $message }}</p>@enderror
        </fieldset>
    </div>
</fieldset>

<style>
    .nf-photo { border: 0; padding: 0; margin: 0; display: grid; gap: var(--s-4); }
    .nf-photo__ways { display: grid; gap: var(--s-5); grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
    .nf-photo__ways .a-field { display: grid; gap: var(--s-2); align-content: start; }
    .nf-photo__focal { display: grid; gap: var(--s-5); grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); align-items: start; }
    .nf-photo__point { border: 0; padding: 0; margin: 0; display: grid; gap: var(--s-2); }
    .nf-photo__frame { position: relative; max-width: 360px; border-radius: var(--r-md); overflow: hidden; cursor: crosshair; }
    .nf-photo__frame img { display: block; width: 100%; height: auto; }
    .nf-photo__marker {
        position: absolute; width: 28px; height: 28px; margin: -14px 0 0 -14px; padding: 0;
        border: 3px solid var(--c-white); border-radius: 50%; background: transparent;
        box-shadow: 0 0 0 2px var(--c-navy); cursor: move;
    }
    .nf-photo__marker:focus-visible { outline: 3px solid var(--c-green-dark); outline-offset: 3px; }
</style>

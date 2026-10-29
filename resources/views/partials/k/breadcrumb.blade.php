{{--
    Breadcrumb and share row (Concept 3), on every public page.

    A page may supply its own trail with @section('breadcrumb') as a list
    of <li> items after "CRASA". Otherwise the trail is built from the
    address: the section the first segment belongs to (its header label),
    then the page title. The home page shows CRASA and Home.
--}}
@php
    $locale = app()->getLocale();
    $segments = array_values(array_filter(explode('/', trim(request()->path(), '/'))));
    if (($segments[0] ?? null) === $locale) {
        array_shift($segments);
    }
    $pageTitle = trim(html_entity_decode(View::yieldContent('title', ''), ENT_QUOTES));
    $section = null;
    if (count($segments) >= 2) {
        foreach (\App\Support\Navigation::primary() as $item) {
            if (\App\Support\HomeData::section($item['url'] ?? null) === $segments[0]) {
                $section = ['label' => $item['label'], 'url' => $item['url']];
                break;
            }
        }
        if ($section === null) {
            $key = 'nav.'.$segments[0];
            $label = __($key);
            if ($label !== $key) {
                $section = ['label' => $label, 'url' => \App\Support\Navigation::url($segments[0])];
            }
        }
    }
@endphp
<div class="k-crumb">
    <div class="k-wrap">
        <nav aria-label="{{ __('chome.breadcrumb') }}">
            <ol>
                <li><a href="{{ \App\Support\Navigation::url('') }}">{{ __('site.acronym') }}</a></li>
                @hasSection('breadcrumb')
                    @yield('breadcrumb')
                @else
                    @if ($segments === [])
                        <li aria-current="page">{{ __('nav.home') }}</li>
                    @else
                        @if ($section && $section['label'] !== $pageTitle)
                            <li><a href="{{ $section['url'] }}">{{ $section['label'] }}</a></li>
                        @endif
                        <li aria-current="page">{{ \Illuminate\Support\Str::limit($pageTitle !== '' ? $pageTitle : ($section['label'] ?? ''), 70) }}</li>
                    @endif
                @endif
            </ol>
        </nav>
        <button class="k-share" type="button" data-k-share data-copied="{{ __('chome.share_copied') }}" aria-label="{{ __('chome.share_label') }}">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/></svg>
            <span>{{ __('chome.share') }}</span>
        </button>
        <span class="c-sr" aria-live="polite" data-k-share-live></span>
    </div>
</div>

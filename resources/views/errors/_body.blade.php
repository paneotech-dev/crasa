{{--
    The body every error page shares: hero with the explanation and a search
    form, then the main sections as cards. Built from url() rather than the
    menu records so it renders in the standalone 500 and 503 pages, where the
    database may be the fault.
--}}
@php
    $links = [
        ['publications', __('nav.publications'), __('cpages_c.browse_publications'), '<path d="M2 4h7a3 3 0 0 1 3 3v14a2 2 0 0 0-2-2H2z"/><path d="M22 4h-7a3 3 0 0 0-3 3v14a2 2 0 0 1 2-2h8z"/>'],
        ['newsroom', __('nav.newsroom'), __('cpages_c.browse_newsroom'), '<path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2zm0 0a2 2 0 0 1-2-2v-9h4"/><path d="M10 6h8M10 10h8M10 14h5"/>'],
        ['members', __('nav.members'), __('cpages_c.browse_members'), '<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15 15 0 0 1 0 20M12 2a15 15 0 0 0 0 20"/>'],
        ['comparator', __('nav.comparator'), __('cpages_c.browse_comparator'), '<path d="M3 3v18h18"/><path d="M7 15v3M12 10v8M17 6v12"/>'],
        ['events', __('nav.events'), __('cpages_c.browse_events'), '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>'],
        ['about', __('nav.about'), __('cpages_c.browse_about'), '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>'],
    ];
@endphp
<section class="u-hero cp-hero cp-error">
    <div class="u-container">
        <div class="u-hero__grid">
            <div>
                <p class="u-eyebrow">{{ __('errors.eyebrow', ['code' => $code]) }}</p>
                <h1 class="u-h1">{{ __('errors.'.$code.'_title') }}</h1>
                <p class="u-lead">{{ __('errors.'.$code.'_body') }}</p>
                <div class="u-actions cp-hero__actions">
                    <a class="u-btn u-btn--primary" href="{{ url('/'.$locale) }}">{{ __('errors.home') }}</a>
                    @if (! empty($back))
                        <a class="u-btn u-btn--secondary" href="{{ $back }}">{{ __('errors.back') }}</a>
                    @endif
                </div>
            </div>
            <form method="GET" action="{{ url('/'.$locale.'/search') }}" class="cp-search__form" role="search">
                <label class="u-label" for="error-q">{{ __('search.label') }}</label>
                <div class="cp-search__field">
                    <input class="u-input" type="search" id="error-q" name="q" aria-describedby="error-q-hint">
                    <button type="submit" class="u-btn u-btn--primary">{{ __('search.submit') }}</button>
                </div>
                <p class="u-help" id="error-q-hint">{{ __('search.hint') }}</p>
            </form>
        </div>
    </div>
</section>

<section class="u-section" aria-labelledby="error-links-title">
    <div class="u-container">
        <h2 class="u-h2" id="error-links-title">{{ __('cpages_c.error_links_title') }}</h2>
        <ul class="u-cols-3 cp-linkcards" role="list">
            @foreach ($links as [$path, $label, $line, $icon])
                <li class="u-card cp-linkcard">
                    <div class="u-card__body">
                        <span class="cp-icon-badge"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">{!! $icon !!}</svg></span>
                        <h3 class="u-card__title"><a href="{{ url('/'.$locale.'/'.$path) }}">{{ $label }}</a></h3>
                        <p class="u-card__text">{{ $line }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
</section>

{{--
    The body every error page shares: hero with the explanation and a search
    form, then the main sections as cards. Built from url() rather than the
    menu records so it renders in the standalone 500 and 503 pages, where the
    database may be the fault.
--}}
@php
    $links = [
        ['publications', __('nav.publications'), __('cpages_c.browse_publications'), 'book'],
        ['newsroom', __('nav.newsroom'), __('cpages_c.browse_newsroom'), 'news'],
        ['members', __('nav.members'), __('cpages_c.browse_members'), 'globe'],
        ['comparator', __('nav.comparator'), __('cpages_c.browse_comparator'), 'chart'],
        ['events', __('nav.events'), __('cpages_c.browse_events'), 'calendar'],
        ['about', __('nav.about'), __('cpages_c.browse_about'), 'info'],
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
                        <h3 class="u-card__title"><a href="{{ url('/'.$locale.'/'.$path) }}">{{ $label }}</a></h3>
                        <p class="u-card__text">{{ $line }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
</section>

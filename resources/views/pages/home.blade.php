{{--
    The home page, Concept 3 (29 October 2026).

    Composed in this template, in the brief's order, and bound to the home
    page's block records so the back office still edits it:

      hero                  hero block: eyebrow, headline, subhead, button,
                            photograph, caption per slide (rotation_items);
                            an optional "focal" setting per slide sets the
                            photograph's focal point (CSS object-position)
      at a glance           key_figures block: values, labels and links
      what we do            pillars block: heading, four headings and lines
      latest guidelines     featured_publications block: heading
      news and updates      newsroom_strip block: heading
      upcoming meetings     slot partials.home.upcoming (events stream)
      comparator            slot partials.home.comparator, else the
                            featured_comparison block as it was
      members               member_grid block: heading and paragraph
      social                slot partials.home.social, else the
                            social_feed block's heading over channel cards

    A section whose block is hidden in the back office is not drawn. The
    order of the sections, the hero figures (PublishedCounts) and the
    "What we do" figures are fixed here.
--}}
@extends('layouts.public')

@php
    $blocks = $page->visibleBlocks->keyBy(fn ($b) => $b->type()->value);
    $t = fn ($block, $field) => $block?->translate($field)?->value;
    $locale = app()->getLocale();
    $nav = fn (string $path) => \App\Support\Navigation::url($path);
    $figures = \App\Support\HomeData::figures();
    $arrow = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
    $arrowSm = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';

    /* ---- Hero slides ---- */
    $hero = $blocks->get('hero');
    $slides = [];
    if ($hero) {
        $defaults = ['center 30%', 'center 30%', 'center 22%'];
        $rotation = array_values($hero->setting('rotation_items', []) ?? []);
        $raw = [['prefix' => '', 'cta_url' => $hero->setting('cta_url'), 'image' => $hero->setting('image'), 'panel' => $hero->setting('panel', 'figures'), 'focal' => $hero->setting('focal')]];
        foreach ($rotation as $i => $s) {
            $raw[] = ['prefix' => 'slide_'.$i.'_', 'cta_url' => $s['cta_url'] ?? null, 'image' => $s['image'] ?? null, 'panel' => $s['panel'] ?? null, 'focal' => $s['focal'] ?? null];
        }
        foreach ($raw as $s) {
            $headline = $hero->translate($s['prefix'].'headline');
            if ($headline === null) {
                continue;
            }
            $asset = \App\Support\MediaReference::isReference($s['image']) ? \App\Support\MediaReference::resolve($s['image']) : null;
            $alt = $asset?->mayAppearIn($locale) ? ($asset->translationValuesFor('alt')[$locale] ?? null) : null;
            $ctaUrl = \App\Support\Navigation::resolve($s['cta_url']);
            $toComparator = $ctaUrl && str_contains($ctaUrl, 'comparator');
            $slides[] = [
                'eyebrow' => $hero->translate($s['prefix'].'eyebrow'),
                'headline' => $headline,
                'subhead' => $hero->translate($s['prefix'].'subhead'),
                'cta_label' => $hero->translate($s['prefix'].'cta_label'),
                'cta_url' => $ctaUrl,
                'second_label' => $toComparator ? __('chome.hero_cta_library') : __('chome.hero_cta_comparator'),
                'second_url' => $toComparator ? $nav('publications') : $nav('comparator'),
                'caption' => $hero->translate($s['prefix'].'caption'),
                'name' => $hero->translate($s['prefix'].'eyebrow')?->value ?? \Illuminate\Support\Str::ucfirst(__('blocks.hero_panel_'.($s['panel'] ?? 'figures').'_name')),
                'asset' => $alt !== null ? $asset : null,
                'alt' => $alt,
                'focal' => $s['focal'] ?: ($defaults[count($slides)] ?? 'center 30%'),
            ];
        }
    }
    $count = count($slides);
    $tiles = \App\Support\HomeData::tiles();

    /* ---- Blocks bound to sections ---- */
    $glance = $blocks->get('key_figures');
    $pillars = $blocks->get('pillars');
    $pubsBlock = $blocks->get('featured_publications');
    $newsBlock = $blocks->get('newsroom_strip');
    $membersBlock = $blocks->get('member_grid');
    $socialBlock = $blocks->get('social_feed');
    $comparisonBlock = $blocks->get('featured_comparison');

    $guidelines = $pubsBlock ? \App\Support\HomeData::guidelines(3) : [];
    $news = $newsBlock ? \App\Support\HomeData::latestNews(4) : [];
    $events = $newsBlock ? \App\Support\HomeData::events(2) : [];
@endphp

@section('title', __('nav.home'))
@if ($d = $t($hero, 'subhead'))
    @section('description', $d)
@endif

@section('content')
<div class="k-home">

{{-- ================= HERO (C6) ================= --}}
@if ($count > 0)
<section class="k-hero" @if ($count > 1) data-k-hero aria-roledescription="carousel" @endif aria-label="{{ __('chome.hero_label') }}">
    <div class="k-wrap">
        <div class="k-hero__box">
            <div class="k-hero__photo">
                @foreach ($slides as $i => $s)
                    <figure class="k-hero__fig @if ($i === 0) is-on @endif" data-k-fig>
                        @if ($s['asset'])
                            @php $a = $s['asset']; @endphp
                            <img src="{{ $a->url(1440) ?? $a->url(960) }}"
                                 srcset="{{ \App\Support\MediaReference::srcset($a) }}"
                                 sizes="(max-width: 900px) 100vw, 660px"
                                 alt="{{ $s['alt'] }}"
                                 @if ($a->width) width="{{ $a->width }}" height="{{ $a->height }}" @endif
                                 style="object-position: {{ $s['focal'] }}"
                                 @if ($i === 0) loading="eager" fetchpriority="high" @else loading="lazy" @endif decoding="async">
                        @else
                            <span class="k-hero__mark" aria-hidden="true"><img src="{{ asset('img/crasa-logo.svg') }}" alt="" width="120" height="120"></span>
                        @endif
                        @if ($s['caption'])<figcaption>{{ $s['caption']->value }}</figcaption>@endif
                    </figure>
                @endforeach
            </div>

            <div class="k-hero__text">
                <div class="k-hero__caps">
                    @foreach ($slides as $i => $s)
                        <div class="k-cap @if ($i === 0) is-on @endif" data-k-slide data-name="{{ $s['name'] }}"
                             @if ($count > 1) role="group" aria-roledescription="{{ __('blocks.hero_slide') }}"
                             aria-label="{{ __('chome.hero_slide', ['index' => $i + 1, 'total' => $count, 'name' => $s['name']]) }}"
                             @if ($i !== 0) aria-hidden="true" inert @endif @endif>
                            @if ($s['eyebrow'])<p class="k-cap__lbl">{{ $s['eyebrow']->value }}</p>@endif
                            @if ($i === 0)
                                <h1 class="k-cap__h">{{ $s['headline']->value }}</h1>
                            @else
                                <p class="k-cap__h" role="heading" aria-level="2">{{ $s['headline']->value }}</p>
                            @endif
                            @if ($s['subhead'])<p class="k-cap__d">{{ $s['subhead']->value }}</p>@endif
                            <div class="k-cap__ctas">
                                @if ($s['cta_label'] && $s['cta_url'])
                                    <a class="k-btn k-btn--green" href="{{ $s['cta_url'] }}">{{ $s['cta_label']->value }} {!! $arrow !!}</a>
                                @endif
                                <a class="k-btn k-btn--ghost" href="{{ $s['second_url'] }}">{{ $s['second_label'] }}</a>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="k-hero__foot">
                    <dl class="k-hero__figs">
                        <div><dt>{{ __('chome.fig_members') }}</dt><dd data-count="{{ $figures['members'] }}">{{ $figures['members'] }}</dd></div>
                        <div><dt>{{ __('chome.fig_committees') }}</dt><dd data-count="{{ $figures['committees'] }}">{{ $figures['committees'] }}</dd></div>
                        <div><dt>{{ __('chome.fig_publications') }}</dt><dd data-count="{{ $figures['publications'] }}">{{ $figures['publications'] }}</dd></div>
                    </dl>

                    @if ($count > 1)
                        <div class="k-hctl" data-k-hero-controls>
                            <button type="button" data-k-prev aria-label="{{ __('chome.hero_prev') }}"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg></button>
                            <div class="k-hctl__dots" role="group" aria-label="{{ __('chome.hero_choose') }}">
                                @foreach ($slides as $i => $s)
                                    <button type="button" class="k-dot" data-k-dot="{{ $i }}" aria-current="{{ $i === 0 ? 'true' : 'false' }}"
                                            aria-label="{{ __('chome.hero_slide', ['index' => $i + 1, 'total' => $count, 'name' => $s['name']]) }}"><span></span></button>
                                @endforeach
                            </div>
                            <button type="button" data-k-next aria-label="{{ __('chome.hero_next') }}"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg></button>
                            <button type="button" class="k-hctl__pause" data-k-pause aria-pressed="false" aria-label="{{ __('chome.hero_pause') }}"
                                    data-label-pause="{{ __('chome.hero_pause') }}" data-label-play="{{ __('chome.hero_play') }}">
                                <svg class="k-i-pause" width="15" height="15" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="6" y="4.5" width="4" height="15" rx="1"/><rect x="14" y="4.5" width="4" height="15" rx="1"/></svg>
                                <svg class="k-i-play" width="15" height="15" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5.5l11 6.5-11 6.5z"/></svg>
                            </button>
                        </div>
                        <div class="c-sr" aria-live="polite" data-k-live data-template="{{ __('chome.hero_live', ['index' => ':index', 'total' => ':total', 'name' => ':name']) }}"></div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Three tiles under the composition, each a concrete fact read
             from the data: the library's guidelines, the comparator's reach
             and the next public meeting. --}}
        @if ($tiles !== [])
            <ul class="k-featured" role="list" aria-label="{{ __('d3_polish.tiles_label') }}">
                @foreach ($tiles as $tile)
                    <li><a class="k-fitem" href="{{ $tile['url'] }}">
                        <span class="k-lbl">{{ $tile['label'] }}</span>
                        <b>{{ $tile['value'] }}</b>
                    </a></li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
@endif

{{-- ================= AT A GLANCE ================= --}}
@if ($glance)
@php
    $glanceItems = collect($glance->setting('items', []) ?? [])->map(fn ($item, $i) => [
        'value' => (string) ($item['value'] ?? ''),
        'label' => $t($glance, 'item_'.$i.'_label'),
        'url' => ! empty($item['link']) ? $nav($item['link']) : null,
    ])->filter(fn ($i) => $i['value'] !== '' && $i['label']);
@endphp
<section class="k-glance" aria-labelledby="k-glance-h">
    <div class="k-wrap">
        <div>
            <h2 id="k-glance-h">{{ __('chome.glance_title') }}</h2>
        </div>
        <div class="k-figs" data-stagger>
            @foreach ($glanceItems as $item)
                @php $n = preg_replace('/\D/', '', $item['value']); $tag = $item['url'] ? 'a' : 'div'; @endphp
                <{{ $tag }} class="k-fig u-reveal" @if ($item['url']) href="{{ $item['url'] }}" @endif>
                    <span class="k-fig__n" @if ($n !== '' && $n === $item['value'] && (int) $n < 1900) data-count="{{ $n }}" @endif>{{ $item['value'] }}</span>
                    <span class="k-fig__t">{{ $item['label'] }}</span>
                </{{ $tag }}>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ================= MISSION + WHAT WE DO (C7) ================= --}}
@if ($pillars)
@php
    $work = [
        ['url' => $nav('publications?type[]=guideline'), 'figure' => $figures['guidelines']],
        ['url' => $nav('comparator'), 'figure' => $figures['indicators']],
        ['url' => $nav('publications?sector=postal'), 'figure' => $figures['postal']],
        ['url' => $nav('committees'), 'figure' => $figures['committees']],
    ];
@endphp
<section class="k-sec" id="what-we-do" aria-labelledby="k-work-h">
    <div class="k-wrap k-mission">
        <div class="k-stmt u-reveal">
            <p class="k-stmt__big">{{ __('chome.footer_blurb') }}</p>
            <div class="k-stmt__ctas">
                <a class="k-btn k-btn--green" href="{{ $nav('members') }}">{{ __('chome.our_members') }}</a>
                <a class="k-btn k-btn--ghost" href="{{ $nav('about') }}">{{ __('nav.about') }}</a>
            </div>
            <div class="k-quick">
                <h3>{{ __('chome.quick_access') }}</h3>
                <a href="{{ $nav('publications') }}">{{ __('chome.quick_library') }} {!! $arrowSm !!}</a>
                <a href="{{ $nav('comparator') }}">{{ __('chome.quick_comparator') }} {!! $arrowSm !!}</a>
                <a href="{{ $nav('members') }}">{{ __('chome.quick_members') }} {!! $arrowSm !!}</a>
                <a href="{{ $nav('events') }}">{{ __('chome.m.events') }} {!! $arrowSm !!}</a>
            </div>
        </div>
        <div>
            <div class="k-sh"><h2 id="k-work-h">{{ $t($pillars, 'heading') ?? __('chome.what_we_do') }}</h2></div>
            <div class="k-acts" data-stagger>
                @foreach ($work as $i => $w)
                    @if ($h = $t($pillars, 'item_'.$i.'_heading'))
                        <article class="k-act u-reveal">
                            <span class="k-act__top">
                                <span class="k-act__fig"><b data-count="{{ $w['figure'] }}">{{ $w['figure'] }}</b> {{ __('chome.work_fig_'.$i) }}</span>
                            </span>
                            <h3><a href="{{ $w['url'] }}">{{ $h }}</a></h3>
                            <p>{{ $t($pillars, 'item_'.$i.'_body') }}</p>
                            <span class="k-act__more" aria-hidden="true">{{ __('chome.work_more_'.$i) }} {!! $arrowSm !!}</span>
                        </article>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</section>
@endif

{{-- ================= LATEST GUIDELINES (C9) ================= --}}
@if ($pubsBlock && $guidelines !== [])
<section class="k-sec k-pubsec" id="publications" aria-labelledby="k-pub-h">
    <div class="k-wrap">
        <div class="k-sh u-reveal">
            <h2 id="k-pub-h">{{ $t($pubsBlock, 'heading') ?? __('chome.pubs_heading') }}</h2>
            <a class="k-all" href="{{ $nav('publications') }}">{{ __('chome.pubs_all') }} {!! $arrow !!}</a>
        </div>
        <div class="k-pgrid" data-stagger>
            @foreach ($guidelines as $p)
                <article class="k-pcard u-reveal">
                    <div class="k-pcard__cv">
                        @if ($p['thumb'])
                            <img src="{{ $p['thumb'] }}" alt="" loading="lazy" decoding="async" width="150" height="212">
                        @else
                            <span class="k-pcard__doc" aria-hidden="true">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h5"/></svg>
                                <b>{{ $p['format'] ?? __('chome.open_link') }}</b>
                            </span>
                        @endif
                    </div>
                    <div class="k-pcard__pt">
                        <span class="k-lbl">{{ collect([$p['type'], $p['sector']])->filter()->implode(', ') }}</span>
                        <h3><a href="{{ $p['url'] }}">{{ $p['title'] }}</a></h3>
                        @if ($p['year'])<span class="k-pcard__ed">{{ __('chome.edition', ['year' => $p['year']]) }}</span>@endif
                        @php $facts = collect([$p['format'], $p['size'], $p['locale']])->filter()->implode(', '); @endphp
                        @if ($facts !== '')<p class="k-pcard__facts">{{ $facts }}</p>@endif
                        @if ($p['download'])
                            <p class="k-pcard__pf">
                                <a class="k-pcard__dl" href="{{ $p['download'] }}" @if ($p['external']) rel="noopener" @endif>{{ $p['external'] ? __('chome.open_link') : __('d3_polish.download_with', ['facts' => collect([$p['format'], $p['size']])->filter()->implode(', ')]) }}<span class="c-sr">: {{ $p['title'] }}</span></a>
                            </p>
                        @endif
                    </div>
                </article>
            @endforeach
            <div class="k-types u-reveal">
                <h3>{{ __('chome.browse_type') }}</h3>
                @foreach ([['annual_report', 'pubs_annual'], ['guideline', 'pubs_guidelines'], ['study', 'pubs_studies'], ['communique', 'pubs_communiques']] as [$type, $key])
                    <a class="k-ty" href="{{ $nav('publications?type[]='.$type) }}">
                        <span><b>{{ __('chome.m.'.$key) }}</b><small>{{ __('chome.m.'.$key.'_d') }}</small></span>
                        {!! $arrowSm !!}
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</section>
@endif

{{-- ================= NEWS AND UPDATES ================= --}}
@if ($newsBlock && $news !== [])
@php $lead = $news[0]; $minis = array_slice($news, 1, 3); @endphp
<section class="k-sec" id="news" aria-labelledby="k-news-h">
    <div class="k-wrap">
        <div class="k-sh u-reveal">
            <h2 id="k-news-h">{{ $t($newsBlock, 'heading') ?? __('chome.news_heading') }}</h2>
            <a class="k-all" href="{{ $nav('newsroom') }}">{{ __('chome.news_all') }} {!! $arrow !!}</a>
        </div>
        <div class="k-ne">
            <div>
                <article class="k-lead u-reveal">
                    <div class="k-lead__ph">
                        @if ($lead['image'])
                            <img src="{{ $lead['image']['src'] }}" @if ($lead['image']['srcset']) srcset="{{ $lead['image']['srcset'] }}" sizes="(max-width: 720px) 100vw, 420px" @endif
                                 @if ($lead['image']['width']) width="{{ $lead['image']['width'] }}" height="{{ $lead['image']['height'] }}" @endif alt="" loading="lazy" decoding="async" @if (! empty($lead['position'])) style="object-position: {{ $lead['position'] }}" @endif>
                        @else
                            @include('partials.k.placeholder')
                        @endif
                    </div>
                    <div class="k-lead__tx">
                        <span class="k-meta">@if ($lead['date'])<time datetime="{{ $lead['iso'] }}">{{ $lead['date'] }}</time>@endif</span>
                        <h3><a href="{{ $lead['url'] }}">{{ $lead['title'] }}</a></h3>
                        @if ($lead['summary'])<p>{{ $lead['summary'] }}</p>@endif
                        <span class="k-rd" aria-hidden="true">{{ __('chome.read_more') }} {!! $arrowSm !!}</span>
                    </div>
                </article>
                @if ($minis !== [])
                    <div class="k-minis" data-stagger>
                        @foreach ($minis as $n)
                            <article class="k-mini u-reveal">
                                <div class="k-mini__ph">
                                    @if ($n['image'])
                                        <img src="{{ $n['image']['src'] }}" @if ($n['image']['width']) width="{{ $n['image']['width'] }}" height="{{ $n['image']['height'] }}" @endif alt="" loading="lazy" decoding="async" @if (! empty($n['position'])) style="object-position: {{ $n['position'] }}" @endif>
                                    @else
                                        @include('partials.k.placeholder', ['small' => true])
                                    @endif
                                </div>
                                <div>
                                    <span class="k-meta">@if ($n['date'])<time datetime="{{ $n['iso'] }}">{{ $n['date'] }}</time>@endif</span>
                                    <h3><a href="{{ $n['url'] }}">{{ $n['title'] }}</a></h3>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
            <aside class="k-events u-reveal" aria-labelledby="k-ev-h">
                <div class="k-events__h"><h3 id="k-ev-h">{{ __('chome.events_heading') }}</h3><a class="k-all" href="{{ $nav('events') }}">{{ __('chome.events_all') }}</a></div>
                @foreach ($events as $e)
                    <a class="k-ev" href="{{ $e['url'] }}">
                        <span class="k-ev__d" aria-hidden="true"><span>{{ $e['month'] }}</span><b>{{ $e['day'] }}</b><small>{{ $e['year'] }}</small></span>
                        <span class="k-ev__i">
                            <span class="k-pill @if ($e['past'] || ($e['status'] ?? 'scheduled') !== 'scheduled') k-pill--grey @endif">{{ ($e['status'] ?? 'scheduled') !== 'scheduled' ? ($e['status'] === 'held' ? __('d3_people.status_held') : __('e.status_'.$e['status'])) : ($e['past'] ? __('chome.events_past') : __('chome.events_upcoming')) }}</span>
                            <b>{{ $e['title'] }}</b>
                            <small><time datetime="{{ $e['iso'] }}">{{ $e['day'] }} {{ $e['month'] }} {{ $e['year'] }}</time>@if ($e['place']), {{ $e['place'] }}@endif</small>
                        </span>
                    </a>
                @endforeach
                <div class="k-ann">
                    <h4>{{ __('chome.related') }}</h4>
                    <a href="{{ $nav('publications?type[]=communique') }}">{{ __('chome.m.pubs_communiques') }}</a>
                    <a href="{{ $nav('events') }}">{{ __('chome.m.events_all') }}</a>
                    <a href="{{ $nav('newsroom#social') }}">{{ __('chome.m.news_social') }}</a>
                </div>
            </aside>
        </div>
    </div>
</section>
@endif

{{-- ================= UPCOMING MEETINGS (C12, events stream) ================= --}}
@includeIf('partials.home.upcoming')

{{-- ================= COMPARATOR (C8, maps and comparator stream) ================= --}}
@if (View::exists('partials.home.comparator'))
    @include('partials.home.comparator')
@elseif ($comparisonBlock)
    <div class="k-cmp-fallback"><x-block :block="$comparisonBlock" ground="white" /></div>
@endif

{{-- ================= MEMBERS (C10) ================= --}}
@if ($membersBlock)
@php $countries = \App\Models\Country::with(['authority.sectors', 'committees'])->orderBy('name')->get(); @endphp
<section class="k-sec k-memsec" id="members" aria-labelledby="k-mem-h">
    <div class="k-wrap">
        <div class="k-sh u-reveal">
            <div><h2 id="k-mem-h">{{ $t($membersBlock, 'heading') ?? __('chome.members_heading') }}</h2></div>
            <a class="k-all" href="{{ $nav('members') }}">{{ __('chome.members_directory') }} {!! $arrow !!}</a>
        </div>
        <div class="k-mx">
            <div class="k-mapbox">
                <x-region-map :countries="$countries" static />
            </div>
            <ul class="k-dir" role="list" data-stagger>
                @foreach ($countries as $country)
                    <li class="k-dcard u-reveal">
                        <span class="k-dcard__logo">
                            @if ($country->authority?->logo_path)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($country->authority->logo_path) }}" alt="" width="48" height="48" loading="lazy" decoding="async">
                            @else
                                <x-flag :iso="$country->iso_code" />
                            @endif
                        </span>
                        <span class="k-dcard__t">
                            <a href="{{ route('members.show', ['locale' => $locale, 'iso' => $country->iso_code]) }}">{{ $country->authority?->acronym ?: $country->name }}</a>
                            <small>{{ $country->name }}</small>
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</section>
@endif

{{-- ================= SOCIAL (C11, back office stream) ================= --}}
@if (View::exists('partials.home.social'))
    @include('partials.home.social')
@elseif ($socialBlock)
    @php $accounts = array_intersect_key(\App\Support\SocialAccounts::all(), array_flip(['x', 'facebook', 'linkedin'])); @endphp
    @if ($accounts !== [])
    <section class="k-follow" aria-labelledby="k-fol-h">
        <div class="k-wrap k-follow__row">
            <div>
                <h2 id="k-fol-h" class="k-follow__h">{{ $t($socialBlock, 'heading') ?? __('chome.social_heading') }}</h2>
            </div>
            <div class="k-sbtns" data-stagger>
                @foreach ($accounts as $network => $url)
                    <a class="k-sbtn u-reveal" href="{{ $url }}" target="_blank" rel="noopener" data-network="{{ $network }}"
                       aria-label="{{ __('blocks.social_account', ['network' => \App\Support\SocialAccounts::label($network)]) }}">
                        <span class="k-sbtn__si"><x-social-mark :network="$network" /></span>
                        <span><b>{{ \App\Support\SocialAccounts::label($network) }}</b>@php $handle = \App\Support\SocialAccounts::handle($network, $url); @endphp<small>{{ str_starts_with($handle, '@') || mb_strlen($handle) <= 22 ? $handle : __('site.acronym') }}</small></span>
                        <svg class="k-sbtn__ar" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M7 17 17 7M9 7h8v8"/></svg>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
    @endif
@endif

</div>
@endsection

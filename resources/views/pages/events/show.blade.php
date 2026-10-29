@extends('layouts.public')

@section('title', $meeting->title)
@section('description', \Illuminate\Support\Str::limit(strip_tags((string) ($meeting->summary ?: $meeting->dateRange().'. '.$meeting->location)), 155))

@push('styles')
    <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/events.css') }}">
@endpush
@push('scripts')
    <script src="{{ \App\Support\Assets::url('js/events.js') }}" defer></script>
@endpush

@php
    $locale = app()->getLocale();
    $g = $meeting->group();
    $url = route('events.show', ['locale' => $locale, 'meeting' => $meeting->slug]);
    $news = $meeting->newsItem && $meeting->newsItem->status === 'published' ? $meeting->newsItem : null;
    $place = \App\Support\EventPlace::coordinates($meeting->location);
    $online = $meeting->isOnline();
    $documents = $meeting->documents->filter(fn ($d) => \Illuminate\Support\Facades\Gate::allows('view', $d))->values();
    $clearanceLabel = $meeting->clearance === \App\Enums\Clearance::Public ? __('e.visibility_public') : __('e.visibility_members');
@endphp

@section('content')
    <section class="u-hero" aria-labelledby="ev-title">
        <div class="u-container u-hero__grid">
            <div>
                <p class="u-eyebrow e-ev-eyebrow">@include('pages.events._badge', ['group' => $g])<span>{{ $g->label() }}</span></p>
                <h1 class="u-h1 e-ev-title @if ($meeting->isCancelled()) e-ev-title--cancelled @endif" id="ev-title">{{ $meeting->title }}</h1>

                @if ($meeting->isCancelled() || $meeting->isPostponed())
                    <div class="e-status e-status--{{ $meeting->status }}" role="status">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                            @if ($meeting->isCancelled())<path d="M6 6l12 12M18 6L6 18"/>@else<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>@endif
                        </svg>
                        <p><strong>{{ $meeting->isCancelled() ? __('e.cancelled_notice') : __('e.postponed_notice') }}</strong>@if ($meeting->status_note) {{ $meeting->status_note }}@endif</p>
                    </div>
                @endif

                <ul class="e-ev-lines">
                    <li>
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
                        <span>@if ($meeting->date_unknown){{ __('events.date_not_stated') }}@else<time datetime="{{ $meeting->starts_on->toDateString() }}">{{ $meeting->dateRange() }}</time>@endif</span>
                    </li>
                    @unless ($meeting->date_unknown)
                        <li>
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                            <span>{{ $meeting->timeRange() ?? __('e.all_day') }}</span>
                        </li>
                    @endunless
                    @if ($meeting->location || $online)
                        <li>
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                            <span>{{ collect([$meeting->venue, strcasecmp((string) $meeting->location, 'Online') === 0 ? __('e.online') : $meeting->location])->filter()->implode(', ') }}</span>
                        </li>
                    @endif
                </ul>

                <div class="e-ev-actions">
                    @include('pages.events._add-to-calendar', ['meeting' => $meeting, 'anchor' => true])
                    <div class="e-share" role="group" aria-labelledby="share-label">
                        <span class="e-addcal__label" id="share-label">{{ __('e.share') }}</span>
                        <button type="button" class="e-addcal__action" data-copy-link="{{ $url }}" data-copied="{{ __('e.copied') }}" aria-controls="share-status">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 10a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></svg>{{ __('e.copy_link') }}</button>
                        <span class="e-share__status" id="share-status" role="status" aria-live="polite"></span>
                    </div>
                </div>
            </div>

            <figure class="u-hero__photo e-ev-photo">
                @if ($news && $news->hero_image_path)
                    @php $photo = \App\Support\ResizedImage::attributes($news->hero_image_path, 'card'); @endphp
                    <img src="{{ $photo['src'] }}" @if ($photo['srcset']) srcset="{{ $photo['srcset'] }}" sizes="(max-width: 991px) 100vw, 40vw" @endif
                         @if ($photo['width'] && $photo['height']) width="{{ $photo['width'] }}" height="{{ $photo['height'] }}" @endif
                         alt="" fetchpriority="high" decoding="async">
                @else
                    {{-- The committee's own placeholder: its code and name on a pale frame. --}}
                    <div class="e-placeholder" style="--e-c: {{ $g->colour }}" aria-hidden="true">
                        <img src="{{ asset('img/crasa-logo.svg') }}" alt="" class="e-placeholder__mark" width="64" height="64">
                        <span class="e-placeholder__code">{{ $g->code }}</span>
                        <span class="e-placeholder__name">{{ $g->label() }}</span>
                    </div>
                @endif
            </figure>
        </div>
    </section>

    <section class="u-section">
        <div class="u-container u-main-side">
            <div class="e-ev-main">
                @if (session('status'))
                    <div class="u-success" role="status">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                @if ($meeting->summary)
                    <h2 class="u-h2 e-ev-h2">{{ __('e.summary') }}</h2>
                    <p class="u-lead">{{ $meeting->summary }}</p>
                @endif

                <h2 class="u-h2 e-ev-h2">{{ __('e.documents') }}</h2>
                @if ($meeting->agenda)
                    <div class="u-panel e-agenda">
                        <h3 class="u-h3">{{ __('events.agenda') }}</h3>
                        @if (\App\Support\PlaceholderContent::isFiller($meeting->agenda))
                            <p class="c-prose c-note">{{ \App\Support\PlaceholderContent::agendaNotice() }}</p>
                        @else
                            <div class="c-prose">{!! app(\App\Services\ContentRenderer::class)->render($meeting->agenda, 'meeting:'.$meeting->id) !!}</div>
                        @endif
                    </div>
                @endif
                @if ($documents->isNotEmpty())
                    <ul class="e-docs">
                        @foreach ($documents as $doc)
                            <li class="e-doc">
                                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5"/></svg>
                                <span class="e-doc__name">{{ $doc->original_filename }}<span class="e-doc__meta">{{ strtoupper(pathinfo((string) $doc->original_filename, PATHINFO_EXTENSION)) }}@if ($doc->size_bytes), {{ \Illuminate\Support\Number::fileSize($doc->size_bytes, 1) }}@endif</span></span>
                                <a class="u-btn u-btn--secondary" href="{{ route('documents.download', ['locale' => $locale, 'document' => $doc->id]) }}">{{ __('e.download') }}<span class="c-sr"> {{ $doc->original_filename }}</span></a>
                            </li>
                        @endforeach
                    </ul>
                @elseif (! $meeting->agenda)
                    <p class="e-muted-box">{{ __('e.no_documents') }}</p>
                @endif

                @if ($related->isNotEmpty())
                    <h2 class="u-h2 e-ev-h2">{{ __('e.related_news') }}</h2>
                    <ul class="{{ $related->count() > 1 ? 'u-cols-2' : '' }} e-cards" role="list">
                        @foreach ($related as $item)
                            @php($t = $item->translate('title'))
                            <li>
                                <article class="u-card">
                                    <div class="u-card__body">
                                        <p class="u-card__label">@if ($item->published_at)<time datetime="{{ $item->published_at->toDateString() }}">{{ $item->published_at->displayed()->locale($locale)->isoFormat('D MMMM YYYY') }}</time>@endif</p>
                                        <h3 class="u-card__title" @if ($t && $t->isFallback()) lang="{{ $t->locale }}" @endif><a href="{{ route('newsroom.show', ['locale' => $locale, 'slug' => $item->slug]) }}">{{ $t?->value }}</a></h3>
                                        @if ($s = $item->translated('summary'))<p class="u-card__text">{{ \Illuminate\Support\Str::limit($s, 160) }}</p>@endif
                                    </div>
                                </article>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <aside class="e-ev-side">
                <div class="u-panel">
                    <dl class="u-facts">
                        <div><dt>{{ __('e.when') }}</dt><dd>{{ $meeting->dateRange() }}</dd></div>
                        <div><dt>{{ __('d3_people.status') }}</dt><dd>{{ $meeting->displayStatusLabel() }}</dd></div>
                        <div><dt>{{ __('e.time_zone') }}</dt><dd>{{ $meeting->timezone ?: \App\Support\LocalTime::zone() }}</dd></div>
                        @if ($meeting->location || $online)
                            <div><dt>{{ __('e.where') }}</dt><dd>{{ collect([$meeting->venue, $meeting->location])->filter()->implode(', ') }}</dd></div>
                        @endif
                        @if ($meeting->organiser)
                            <div><dt>{{ __('e.organiser') }}</dt><dd>{{ $meeting->organiser }}</dd></div>
                        @endif
                        <div><dt>{{ __('e.committee') }}</dt><dd>{{ $g->label() }}</dd></div>
                        <div><dt>{{ __('e.visibility') }}</dt><dd>{{ $clearanceLabel }}</dd></div>
                    </dl>
                    @if ($online && $meeting->online_url && ! $meeting->hasEnded() && ! $meeting->isCancelled())
                        <p class="e-side-btn"><a class="u-btn u-btn--secondary" href="{{ $meeting->online_url }}" rel="noopener" target="_blank">{{ __('e.join_online') }}<span class="c-sr"> {{ __('events.new_tab') }}</span></a></p>
                    @endif
                </div>

                @if ($place)
                    <figure class="e-map">
                        <div class="e-map__frame" role="img" aria-label="{{ __('e.map_label', ['place' => $meeting->location]) }}">
                            <div class="e-map__tiles">
                                @foreach (\App\Support\EventPlace::tiles($place['lat'], $place['lng']) as $tile)
                                    <img src="{{ $tile['url'] }}" alt="" width="256" height="256" loading="lazy" decoding="async" style="left: {{ $tile['left'] }}px; top: {{ $tile['top'] }}px">
                                @endforeach
                            </div>
                            <svg class="e-map__pin" viewBox="0 0 24 24" width="36" height="36" aria-hidden="true"><path d="M12 22s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 15.8 12 22 12 22z" fill="#0B5F3E" stroke="#FFFFFF" stroke-width="1.5"/><circle cx="12" cy="10.5" r="2.6" fill="#FFFFFF"/></svg>
                        </div>
                        <figcaption class="e-map__caption">
                            <span>{{ html_entity_decode(config('crasa.map.tile_attribution'), ENT_QUOTES | ENT_HTML5, 'UTF-8') }}</span>
                            <a href="https://www.openstreetmap.org/?mlat={{ $place['lat'] }}&amp;mlon={{ $place['lng'] }}#map=12/{{ $place['lat'] }}/{{ $place['lng'] }}" target="_blank" rel="noopener">{{ __('e.open_map') }}<span class="c-sr"> {{ __('events.new_tab') }}</span></a>
                        </figcaption>
                    </figure>
                @endif

                @include('partials.events.registration-block', ['meeting' => $meeting])
            </aside>
        </div>
    </section>

    <nav class="u-section u-section--pale e-prevnext" aria-label="{{ __('e.back_to_events') }}">
        <div class="u-container e-prevnext__grid">
            @if ($previous)
                <a class="u-card e-prevnext__link" href="{{ route('events.show', ['locale' => $locale, 'meeting' => $previous->slug]) }}" rel="prev">
                    <span class="u-card__label">{{ __('e.previous_event') }}</span>
                    <span class="e-prevnext__title">{{ $previous->title }}</span>
                    <span class="e-prevnext__meta">{{ $previous->dateRange() }}</span>
                </a>
            @else
                <span></span>
            @endif
            <a class="u-btn u-btn--secondary e-prevnext__all" href="{{ route('events', ['locale' => $locale]) }}">{{ __('e.back_to_events') }}</a>
            @if ($next)
                <a class="u-card e-prevnext__link e-prevnext__link--next" href="{{ route('events.show', ['locale' => $locale, 'meeting' => $next->slug]) }}" rel="next">
                    <span class="u-card__label">{{ __('e.next_event') }}</span>
                    <span class="e-prevnext__title">{{ $next->title }}</span>
                    <span class="e-prevnext__meta">{{ $next->dateRange() }}</span>
                </a>
            @else
                <span></span>
            @endif
        </div>
    </nav>
@endsection

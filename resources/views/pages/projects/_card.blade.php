{{-- One project as a card (S8, UI system). $project, $usedImages (by reference through $seen). --}}
@php
    $locale = app()->getLocale();
    $ptitle = $project->translate('title');
    $psummary = $project->translate('summary');
    $line = $psummary ? \Illuminate\Support\Str::limit(trim($psummary->value, " \t\n\"'\u{201C}\u{201D}\u{00AB}\u{00BB}"), 140) : null;
    $sectorSlug = $project->sector?->slug;
    $partners = $project->partner ? array_filter(array_map('trim', explode(',', $project->partner))) : [];
    $current = $project->isCurrent();
    $progress = null;
    if ($project->starts_on && $project->ends_on && $project->ends_on->gt($project->starts_on)) {
        $total = $project->starts_on->diffInDays($project->ends_on);
        $done = $project->starts_on->diffInDays(min(now(), $project->ends_on), false);
        $progress = max(0, min(100, (int) round($done / $total * 100)));
    }
@endphp
<li class="u-card cp-proj-card">
    <figure class="u-card__media">
        @if ($image)
            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image) }}" alt="" loading="lazy" decoding="async" width="480" height="300">
        @else
            {{-- No photograph: the CRASA mark on white, never a labelled frame. --}}
            <span class="cp-placeholder" aria-hidden="true"><img src="{{ asset('img/crasa-logo.svg') }}" alt="" width="56" height="56"></span>
        @endif
    </figure>
    <div class="u-card__body">
        <div class="cp-proj-card__top">
            @if ($project->programme || $project->sector)<span class="u-card__label">{{ collect([$project->programme, $project->sector?->name])->filter()->implode(', ') }}</span>@endif
            <span class="u-pill {{ $current ? 'u-pill--ok' : 'u-pill--muted' }}">
                @if ($current)
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/></svg>
                @else
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5 9-10"/></svg>
                @endif
                {{ $current ? __('cpages_a.status_current') : __('cpages_a.status_completed') }}
            </span>
        </div>
        @if ($ptitle)
            <h3 class="u-card__title"><a href="{{ route('projects.show', ['locale' => $locale, 'project' => $project->slug]) }}" @if ($ptitle->isFallback()) lang="{{ $ptitle->locale }}" @endif>{{ $ptitle->value }}</a></h3>
        @endif
        @if ($line)<p class="u-card__text">{{ $line }}</p>@endif
        @if ($partners !== [])
            <ul class="cp-proj-partners" role="list" aria-label="{{ __('cpages_a.partners') }}">
                @foreach ($partners as $partner)<li>{{ $partner }}</li>@endforeach
            </ul>
        @endif
        @if ($progress !== null)
            <div class="cp-proj-time">
                <div class="cp-proj-time__bar" role="img" aria-label="{{ __('cpages_a.progress', ['percent' => $progress]) }}"><span style="width: {{ $progress }}%"></span></div>
                <div class="cp-proj-time__ends"><span>{{ $project->starts_on->locale($locale)->isoFormat('MMM YYYY') }}</span><span>{{ $project->ends_on->locale($locale)->isoFormat('MMM YYYY') }}</span></div>
            </div>
        @elseif ($project->starts_on)
            <div class="u-card__meta"><span>{{ __('cpages_a.started', ['date' => $project->starts_on->locale($locale)->isoFormat('MMMM YYYY')]) }}</span></div>
        @endif
    </div>
    <div class="u-card__actions">
        <span class="u-btn u-btn--secondary cp-proj-open" aria-hidden="true">{{ __('cpages_a.open') }}</span>
    </div>
</li>

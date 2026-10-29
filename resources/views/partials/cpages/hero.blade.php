{{--
    Short page hero (UI system). Parameters:
    $eyebrow, $title (h1), $lead (one line), $figures ([['value'=>, 'label'=>]]),
    $photoName (ImageLibrary slot) + $photoAlt, or $photo (['src'=>, 'alt'=>, 'srcset'=>?, 'position'=>?]), $tone ('green'|'blue'),
    $heroActions (html string, optional), $heroAside (html string in place of photo).
--}}
@php
    $figures = array_values(array_filter($figures ?? [], fn ($f) => ($f['value'] ?? '') !== '' && $f['value'] !== null));
    $photo = $photo ?? null;
    $heroAside = $heroAside ?? null;
@endphp
<section class="u-hero {{ ($tone ?? 'green') === 'blue' ? 'u-hero--blue' : '' }} cp-hero">
    <div class="u-container">
        <div class="u-hero__grid">
            <div class="cp-hero__text">
                @if (! empty($eyebrow))<p class="u-eyebrow">{{ $eyebrow }}</p>@endif
                <h1 class="u-h1">{{ $title }}</h1>
                @if (! empty($lead))<p class="u-lead">{{ $lead }}</p>@endif
                @if ($figures !== [])
                    <dl class="u-figures">
                        @foreach ($figures as $figure)
                            <div class="u-figure"><dd>{{ $figure['value'] }}</dd><dt>{{ $figure['label'] }}</dt></div>
                        @endforeach
                    </dl>
                @endif
                @if (! empty($heroActions))<div class="u-actions cp-hero__actions">{!! $heroActions !!}</div>@endif
            </div>
            @if (! empty($photoName) && \App\Support\ImageLibrary::has($photoName))
                <figure class="u-hero__photo">
                    <x-responsive-image :name="$photoName" :alt="$photoAlt ?? ''" :decorative="empty($photoAlt)" sizes="(min-width: 992px) 480px, 100vw" loading="eager" fetchpriority="high" />
                </figure>
            @elseif ($photo)
                <figure class="u-hero__photo">
                    <img src="{{ $photo['src'] }}" @if (! empty($photo['srcset'])) srcset="{{ $photo['srcset'] }}" sizes="(max-width: 991px) 100vw, 480px" @endif alt="{{ $photo['alt'] ?? '' }}" width="{{ $photo['width'] ?? 800 }}" height="{{ $photo['height'] ?? 600 }}" fetchpriority="high" style="object-position: {{ $photo['position'] ?? '50% 40%' }}">
                </figure>
            @elseif ($heroAside)
                <div class="cp-hero__aside">{!! $heroAside !!}</div>
            @endif
        </div>
    </div>
</section>

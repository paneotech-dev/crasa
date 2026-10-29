{{-- Call-to-action band (UI system). $ctaTitle, $ctaText?, $ctaPrimary [label,url], $ctaSecondary [label,url]? --}}
<section class="u-cta cp-cta" aria-labelledby="cp-cta-title">
    <div class="u-container u-cta__inner">
        <div>
            <h2 class="u-cta__title" id="cp-cta-title">{{ $ctaTitle }}</h2>
            @if (! empty($ctaText))<p class="u-cta__text">{{ $ctaText }}</p>@endif
        </div>
        <div class="u-actions">
            @if (! empty($ctaPrimary))<a class="u-btn u-btn--primary" href="{{ $ctaPrimary[1] }}">{{ $ctaPrimary[0] }}</a>@endif
            @if (! empty($ctaSecondary))<a class="u-btn u-btn--secondary" href="{{ $ctaSecondary[1] }}">{{ $ctaSecondary[0] }}</a>@endif
        </div>
    </div>
</section>

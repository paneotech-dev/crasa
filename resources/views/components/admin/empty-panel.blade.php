{{--
    A panel whose data source does not exist yet.

    It says what the panel will show and what has to be in place before it
    can. It never draws a chart of invented numbers: on a platform whose
    figures get quoted in ministerial briefs, one fabricated chart makes
    every real one unusable.

    NOT DASHED. A dashed border is this platform's "nothing has been filed
    here yet" -- a state the reader can change by filing something. These
    three are a different thing: the platform has no analytics collection,
    so no amount of using it will fill them, and a border that suggests
    otherwise is an invitation to wait. A solid card that states the
    dependency is the honest shape. Ruled 10 September 2026.
--}}
@props(['heading', 'title', 'body'])

<section class="a-card a-card--pending">
    <div class="a-card__head">
        <h2>{{ $heading }}</h2>
    </div>

    <div class="a-nosource">
        <span class="a-nosource__mark" aria-hidden="true">
            <x-shell-icon name="indicators" :size="20" />
        </span>
        <span>
            <span class="a-nosource__title">{{ $title }}</span>
            <span class="a-nosource__body">{{ $body }}</span>
        </span>
    </div>
</section>

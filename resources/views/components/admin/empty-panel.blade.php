{{--
    A panel whose data source does not exist yet.

    It says what the panel will show and what has to be in place before it
    can. It never draws a chart of invented numbers: on a platform whose
    figures get quoted in ministerial briefs, one fabricated chart makes
    every real one unusable.
--}}
@props(['heading', 'title', 'body'])

<div class="a-panel">
    <div class="a-panel__head">
        <h2>{{ $heading }}</h2>
    </div>
    <div class="a-empty">
        <div class="a-empty__title">{{ $title }}</div>
        <div class="a-empty__body">{{ $body }}</div>
    </div>
</div>

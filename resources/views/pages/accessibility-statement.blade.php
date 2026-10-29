{{--
    Accessibility statement, G5. Short hero with the two facts a reader
    looks for (the standard, the last review), then two columns: a table of
    contents that stays in view, and the statement. Known limitations are a
    table because each has a date found and a next step. Ends with the
    contact band.
--}}
@extends('layouts.public')

@php
    $s = $statement;
    $order = ['standard', 'done', 'options', 'tested', 'limits', 'report', 'review'];
    $email = \App\Support\SiteContact::email();
    $phone = \App\Support\SiteContact::phone();
@endphp

@section('title', $s['title'])
@section('description', $s['meta_description'])

@section('content')
    <section class="u-hero" aria-labelledby="statement-title">
        <div class="u-container">
            <div class="u-hero__grid">
                <div>
                    <p class="u-eyebrow">{{ $s['eyebrow'] }}</p>
                    <h1 class="u-h1" id="statement-title">{{ $s['title'] }}</h1>
                    <p class="u-lead">{{ $s['lead'] }}</p>
                    <dl class="u-figures">
                        <div class="u-figure">
                            <dt>{{ $s['fig_standard_label'] }}</dt>
                            <dd><span lang="en">WCAG 2.1</span> AA</dd>
                        </div>
                        <div class="u-figure">
                            <dt>{{ $s['fig_review_label'] }}</dt>
                            <dd><time datetime="2026-10-29">{{ $s['fig_review'] }}</time></dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </section>

    <div class="u-section">
        <div class="u-container g-statement">
            <nav class="g-statement__toc" aria-labelledby="statement-toc">
                <div class="u-panel">
                    <h2 class="g-statement__toc-title" id="statement-toc">{{ $s['toc'] }}</h2>
                    <ol>
                        @foreach ($order as $key)
                            <li><a href="#{{ $key }}">{{ $s['sections'][$key]['title'] }}</a></li>
                        @endforeach
                    </ol>
                </div>
            </nav>

            <div class="g-statement__body">
                @foreach ($order as $key)
                    @php $section = $s['sections'][$key]; @endphp
                    <section class="g-statement__section" id="{{ $key }}" aria-labelledby="{{ $key }}-title" tabindex="-1">
                        <h2 class="u-h2" id="{{ $key }}-title">{{ $section['title'] }}</h2>

                        @if (! empty($section['intro']))
                            <p class="u-text">{{ $section['intro'] }}</p>
                        @endif

                        @foreach ($section['body'] ?? [] as $paragraph)
                            <p class="u-text">{{ $paragraph }}</p>
                        @endforeach

                        @if ($key === 'limits')
                            <div class="u-table-frame g-statement__table" role="region" aria-labelledby="{{ $key }}-title" tabindex="0">
                                <table class="u-table">
                                    <caption class="c-sr">{{ $section['title'] }}</caption>
                                    <thead>
                                        <tr>
                                            <th scope="col">{{ $section['col_what'] }}</th>
                                            <th scope="col">{{ $section['col_found'] }}</th>
                                            <th scope="col">{{ $section['col_next'] }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($section['items'] as $item)
                                            <tr>
                                                <th scope="row">
                                                    <span class="g-statement__what">{{ $item['what'] }}</span>
                                                    <span class="g-statement__detail">{{ $item['detail'] }}</span>
                                                </th>
                                                <td>{{ $item['found'] }}</td>
                                                <td>{{ $item['next'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @elseif (! empty($section['items']))
                            <ul class="g-statement__list">
                                @foreach ($section['items'] as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        @endif

                        @if ($key === 'report')
                            <dl class="g-statement__contact">
                                <div class="u-panel">
                                    <dt>{{ $section['email'] }}</dt>
                                    <dd><a href="mailto:{{ $email }}">{{ $email }}</a></dd>
                                </div>
                                <div class="u-panel">
                                    <dt>{{ $section['phone'] }}</dt>
                                    <dd><a href="tel:{{ preg_replace('/[^+\d]/', '', $phone) }}">{{ $phone }}</a></dd>
                                </div>
                            </dl>
                            <p class="u-text">{{ $section['reply'] }}</p>
                            <p><a class="u-btn u-btn--secondary" href="{{ route('contact') }}">{{ $section['form'] }}</a></p>
                        @endif
                    </section>
                @endforeach
            </div>
        </div>
    </div>

    <section class="u-cta" aria-labelledby="statement-cta">
        <div class="u-container u-cta__inner">
            <div>
                <h2 class="u-cta__title" id="statement-cta">{{ $s['cta_title'] }}</h2>
                <p class="u-cta__text">{{ $s['cta_text'] }}</p>
            </div>
            <a class="u-btn u-btn--primary" href="mailto:{{ $email }}">{{ $s['cta_button'] }}</a>
        </div>
    </section>
@endsection

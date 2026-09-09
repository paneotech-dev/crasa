{{-- The regulatory library. WS-05, screen 07. --}}
@extends('layouts.public')

@section('title', __('publications.title'))
@section('description', __('publications.intro'))

@section('content')
    {{-- No photograph. The library has five images and none of them is a
         regulatory library: a test bench, a courier, a parcel handover and a
         team at a laptop. A picture that is not about the section it opens
         is a lie about what the reader is looking at. --}}
    <x-section-band
        :eyebrow="__('publications.band_eyebrow')"
        :title="__('publications.band_heading')"
        :lead="__('publications.intro')"
        :counts="\App\Support\SectionCounts::publications()"
    />

    <div class="c-section">
        <div class="c-page">

            {{-- The filters answer without reloading the page, and they
                 are chip links, so the floor is a page that filters and
                 paginates with no script at all. See public/js/filters.js:
                 nothing there draws a control. --}}
            <div data-live-filters
                 data-live-swap="#library-filters,#library-results"
                 data-live-status="#library-count">
            <nav class="c-filters" id="library-filters" aria-label="{{ __('publications.filters') }}">
                <x-facet
                    :legend="__('publications.type')"
                    field="type"
                    :active="$filters['type']"
                    :counts="$counts['type']"
                    :options="collect($types)->map(fn ($t) => ['value' => $t->value, 'label' => __($t->labelKey())])"
                />
                {{-- Screen 07 draws Sector first among its facets. --}}
                <x-facet
                    :legend="__('publications.sector')"
                    field="sector"
                    :active="$filters['sector']"
                    :counts="$counts['sector']"
                    :options="$sectors->map(fn ($s) => ['value' => $s->slug, 'label' => $s->name])"
                />
                <x-facet
                    :legend="__('publications.country')"
                    field="country"
                    :active="$filters['country']"
                    :counts="$counts['country']"
                    :options="$countries->map(fn ($c) => ['value' => $c->iso_code, 'label' => $c->name])"
                />
                <x-facet
                    :legend="__('publications.committee')"
                    field="committee"
                    :active="$filters['committee']"
                    :counts="$counts['committee']"
                    :options="$committees->map(fn ($c) => ['value' => $c->slug, 'label' => $c->name])"
                />
                {{-- "Available in". A regulator who works in French needs
                     to filter the library to what exists in French, and
                     that is a trilingual obligation rather than a
                     convenience. It reads the attached document's language,
                     not the record's translations: the title may be
                     translated while the PDF is not, and it is the PDF the
                     reader came for. --}}
                <x-facet
                    :legend="__('publications.language')"
                    field="language"
                    :active="$filters['language']"
                    :counts="$counts['language']"
                    :options="collect($languages)->map(fn ($code) => ['value' => $code, 'label' => \App\Support\Locales::nameOf($code)])"
                />
                <x-facet
                    :legend="__('publications.year')"
                    field="year"
                    :active="$filters['year']"
                    :counts="$counts['year']"
                    :options="$years->map(fn ($y) => ['value' => $y, 'label' => $y])"
                />
            </nav>

            {{-- In the document before any exchange begins, so replacing
                 its text is announced. A results count rebuilt from
                 scratch is a results count a screen reader never hears. --}}
            <p class="c-results" id="library-count" role="status">{{ trans_choice('publications.result_count', $publications->total(), ['count' => $publications->total()]) }}</p>

            <div id="library-results">
            @if ($publications->isEmpty())
                @include('blocks._empty', [
                    'title' => __('publications.empty_title'),
                    'body' => __('publications.empty_body'),
                    'actionLabel' => __('publications.clear_filters'),
                    'actionUrl' => route('publications', ['locale' => app()->getLocale()]),
                ])
            @else
                <div class="b-rows">
                    @foreach ($publications as $publication)
                        @include('pages.publications._row', ['publication' => $publication])
                    @endforeach
                </div>

                <div class="c-pagination">{{ $publications->links() }}</div>
            @endif
            </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ \App\Support\Assets::url('js/filters.js') }}" defer></script>
@endpush

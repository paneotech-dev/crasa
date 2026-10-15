{{--
    A comparison the reader can drive, on the homepage.

    RP-10 is the platform's flagship tool and this block used to be a
    picture of it: a fixed table under an intro promising an interaction
    that only existed on another page. Two member states and one indicator
    are now selectable here, and the full four-state comparator is one link
    away with the same selection already made.

    The whole state is in the query string, so the URL a reader pastes into
    an email opens on the same two states and the same figure.

    The table is the chart. Screen 03's rule holds: the comparator must be
    usable without seeing a graph, and "Not reported" is set apart from a
    figure because it is not one.
--}}
@php
    $heading = $block->translate('heading');
    $intro = $block->translate('intro');
    $rows = $rows ?? collect();
    $indicator = $indicator ?? null;
    $choices = $choices ?? collect();
    $states = $states ?? collect();
    $selected = $selected ?? [];
    $anchor = $block->anchor ?: 'compare';
@endphp

<section class="b" id="{{ $anchor }}" @if (! empty($ground)) data-ground="{{ $ground }}" @endif>
    <div class="b__inner">
        @if ($heading)
            <h2 class="b__heading">{{ $heading->value }}</h2>
        @endif
        @if ($intro)
            <p class="b__intro">{{ $intro->value }}</p>
        @endif

        @if ($rows->isEmpty())
            @include('blocks._empty', [
                'editorOnly' => true,
                'title' => __('blocks.comparison_empty_title'),
                'body' => __('blocks.comparison_empty_body'),
            ])
        @else
            <div data-live-filters
                 data-live-swap="#home-comparison"
                 data-live-status="#home-comparison-status">

                {{-- Outside the swapped region, so its text change is
                     announced rather than rebuilt into silence. --}}
                <p class="c-results" id="home-comparison-status" role="status">{{ __('blocks.comparison_status', [
                    'indicator' => $indicator->name,
                    'a' => $rows->get(0)['country'] ?? '',
                    'b' => $rows->get(1)['country'] ?? '',
                ]) }}</p>

                {{-- A GET form. With no script the homepage reloads to this
                     section; with one, only the table changes. --}}
                <form class="b-compare__controls" method="get" action="{{ url()->current() }}#{{ $anchor }}">
                    <div class="b-compare__control">
                        <label for="cmp-indicator">{{ __('blocks.comparison_indicator') }}</label>
                        <select class="c-select" id="cmp-indicator" name="cmp_indicator">
                            @foreach ($choices as $choice)
                                <option value="{{ $choice->slug }}" @selected(($selected['indicator'] ?? null) === $choice->slug)>{{ $choice->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    @foreach (['a', 'b'] as $slot)
                        <div class="b-compare__control">
                            <label for="cmp-{{ $slot }}">{{ __('blocks.comparison_state_'.$slot) }}</label>
                            <select class="c-select" id="cmp-{{ $slot }}" name="cmp_{{ $slot }}">
                                @foreach ($states as $state)
                                    <option value="{{ $state->iso_code }}" @selected(($selected[$slot] ?? null) === $state->iso_code)>{{ $state->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach

                    <button class="c-btn c-btn--secondary" type="submit" data-live-submit>{{ __('blocks.comparison_apply') }}</button>
                </form>

                <div id="home-comparison">
                    <p class="b-compare__indicator">
                        {{ $indicator->name }}@if ($indicator->unitLabel() && ! str_contains(mb_strtolower($indicator->name), mb_strtolower($indicator->unitLabel())))<span class="c-cmp__indicator-unit">{{ $indicator->unitLabel() }}</span>@endif
                    </p>

                    <div class="b-compare__scroll">
                        <table class="b-compare__table">
                            <caption class="c-sr">{{ $indicator->name }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('blocks.member_state') }}</th>
                                    <th scope="col">{{ __('blocks.value') }}</th>
                                    <th scope="col">{{ __('publications.published') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    <tr>
                                        <th scope="row">
                                            <a href="{{ route('members.show', ['locale' => app()->getLocale(), 'iso' => $row['iso']]) }}">{{ $row['country'] }}</a>
                                        </th>
                                        {{-- "Not reported" is set apart from a figure,
                                             because it is not one. --}}
                                        <td class="is-numeric" @unless ($row['reported']) data-missing @endunless>
                                            {{ $row['value'] ?? __('blocks.not_reported') }}
                                            @if ($row['reported'] && $row['confidence'] !== 'reported')
                                                <span class="c-pill c-pill--pending">{{ $row['confidence'] }}</span>
                                            @endif
                                        </td>
                                        <td class="is-numeric">{{ $row['period'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Every figure on this platform says where its definition
                         came from. A comparison without one is a number without
                         provenance. --}}
                    @if ($indicator->source_standard)
                        <p class="c-source">{{ $indicator->source_standard }}</p>
                    @endif

                    {{-- The homepage is an entry point to RP-10, not a picture of
                         it: the link opens the comparator on exactly these member
                         states and this indicator. --}}
                    @if (! empty($comparator_url))
                        <p><a class="c-btn c-btn--secondary" href="{{ $comparator_url }}" data-live-skip>{{ __('comparator.open_with_these') }}</a></p>
                    @endif

                    {{-- The demonstration notice was here. Removed on a
                         ruling of 9 September 2026: sample data is flagged
                         in the database and disclosed verbally, not badged
                         on the page. See OPEN_ITEMS.md. --}}
                </div>
            </div>
        @endif
    </div>
</section>

@once
    @push('scripts')
        <script src="{{ \App\Support\Assets::url('js/filters.js') }}" defer></script>
    @endpush
@endonce

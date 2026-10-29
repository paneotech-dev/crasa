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
            {{-- 29 October 2026. A GET form, and that is the floor: with
                 no script, changing a selector and pressing the button
                 reloads the homepage at this block (the #anchor keeps the
                 reader here). With a script, each change fetches only the
                 table from the fragment endpoint and swaps it in; the
                 button goes, and the status line below announces the new
                 comparison. --}}
            <div class="b-compare" data-home-compare
                 data-fragment="{{ route('home.comparison', ['locale' => app()->getLocale()]) }}">

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

                    <button class="c-btn c-btn--secondary b-compare__apply" type="submit" data-cmp-submit>{{ __('blocks.comparison_apply') }}</button>
                </form>

                {{-- Outside the swapped region, so a change of its text is
                     announced rather than rebuilt into silence. --}}
                <p class="c-sr" id="home-comparison-status" role="status" aria-live="polite">{{ __('blocks.comparison_status', [
                    'indicator' => $indicator->name,
                    'a' => $rows->get(0)['country'] ?? '',
                    'b' => $rows->get(1)['country'] ?? '',
                ]) }}</p>

                <div id="home-comparison" data-cmp-target>
                    @include('blocks._comparison-result')
                </div>
            </div>
        @endif
    </div>
</section>

@once
    @push('scripts')
        <script src="{{ \App\Support\Assets::url('js/home-compare.js') }}" defer></script>
    @endpush
@endonce

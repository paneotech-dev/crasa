{{--
    A pinned comparator view. The chart always has a table equivalent: the
    comparator must be usable without seeing the graph.

    Indicators arrive in a later stage. Until then this is the empty state.
--}}
@php
    $heading = $block->translate('heading');
    $intro = $block->translate('intro');
    $rows = $rows ?? collect();
    $indicator = $indicator ?? null;
@endphp

<section class="b" @if (! empty($ground)) data-ground="{{ $ground }}" @endif>
    <div class="b__inner">
        @if ($heading)
            <h2 class="b__heading">{{ $heading->value }}</h2>
        @endif
        @if ($intro)
            <p class="b__intro">{{ $intro->value }}</p>
        @endif

        @if ($rows->isEmpty())
            @include('blocks._empty', [
                'title' => __('blocks.comparison_empty_title'),
                'body' => __('blocks.comparison_empty_body'),
            ])
        @else
            @if ($indicator)
                <p class="b-compare__indicator">{{ $indicator->name }}</p>
            @endif

            <div class="b-compare__scroll">
                <table class="b-compare__table">
                    <caption class="c-sr">{{ $indicator?->name ?? __('blocks.comparison_caption') }}</caption>
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
                                <th scope="row">{{ $row['country'] }}</th>
                                <td class="is-numeric">
                                    {{ $row['value'] ?? __('blocks.not_reported') }}
                                    @if ($row['confidence'] !== 'reported')
                                        <span class="c-pill c-pill--pending">{{ $row['confidence'] }}</span>
                                    @endif
                                </td>
                                <td class="is-numeric">{{ $row['period'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($indicator)
                {{-- Every figure on this platform says where its definition
                     came from. A comparison without one is a number without
                     provenance. --}}
                <p class="c-source">{{ $indicator->source_standard }}</p>
            @endif

            @if ($rows->contains(fn ($r) => $r['is_sample']))
                <div class="c-notice c-notice--pending" role="status">
                    <div>
                        <div class="c-notice__title">{{ __('indicators.demo_title') }}</div>
                        <div class="c-notice__body">{{ __('indicators.demo_body') }}</div>
                    </div>
                </div>
            @endif
        @endif
    </div>
</section>

{{--
    A pinned comparator view. The chart always has a table equivalent: the
    comparator must be usable without seeing the graph.

    Indicators arrive in a later stage. Until then this is the empty state.
--}}
@php
    $heading = $block->translate('heading');
    $intro = $block->translate('intro');
    $rows = $rows ?? collect();
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
            <div class="b-compare__scroll">
                <table class="b-compare__table">
                    <caption class="c-sr">{{ $heading?->value ?? __('blocks.comparison_caption') }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">{{ __('blocks.member_state') }}</th>
                            <th scope="col">{{ __('blocks.value') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <th scope="row">{{ $row['country'] }}</th>
                                <td class="is-numeric">{{ $row['value'] ?? __('blocks.not_reported') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</section>

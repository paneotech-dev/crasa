{{--
    The homepage comparison's result: the indicator, the table, the source
    and the way into the full comparator. Rendered inside the block on the
    homepage, and alone by HomeComparisonController for the block's script,
    so the two can never differ.

    The data attributes carry the selection the server actually resolved
    (the same member state twice falls back to another one), so the script
    can set the selectors to what the table shows and announce it.
--}}
<div class="b-compare__result"
     data-cmp-result
     data-indicator="{{ $selected['indicator'] ?? '' }}"
     data-a="{{ $selected['a'] ?? '' }}"
     data-b="{{ $selected['b'] ?? '' }}"
     data-status="{{ __('blocks.comparison_status', [
         'indicator' => $indicator->name,
         'a' => $rows->get(0)['country'] ?? '',
         'b' => $rows->get(1)['country'] ?? '',
     ]) }}">
    <h3 class="b-compare__indicator">
        {{ $indicator->name }}@if ($indicator->unitLabel() && ! str_contains(mb_strtolower($indicator->name), mb_strtolower($indicator->unitLabel())))<span class="c-cmp__indicator-unit">{{ $indicator->unitLabel() }}</span>@endif
    </h3>

    <div class="b-compare__scroll">
        {{-- The value sits next to the member state it belongs to, left
             aligned, and the year has a column of its own: a reader reads
             a row, not a spreadsheet stretched across the page. --}}
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
                        <td class="b-compare__value" @unless ($row['reported']) data-missing @endunless>
                            {{ $row['value'] ?? __('blocks.not_reported') }}
                            @if ($row['reported'] && $row['confidence'] !== 'reported')
                                <span class="c-pill c-pill--pending">{{ \Illuminate\Support\Facades\Lang::has('comparator.provenance_'.$row['confidence']) ? __('comparator.provenance_'.$row['confidence']) : $row['confidence'] }}</span>
                            @endif
                        </td>
                        <td class="b-compare__year">{{ $row['period'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Every figure on this platform says where its definition came from.
         A comparison without one is a number without provenance. --}}
    @if ($indicator->source_standard)
        <p class="c-source">{{ $indicator->source_standard }}</p>
    @endif

    <x-indicator-note :indicators="$indicator" />

    {{-- The homepage is an entry point to the comparator, not a picture of
         it: the link opens it on exactly these member states and this
         indicator. --}}
    @if (! empty($comparator_url))
        <p class="b-compare__open"><a class="c-btn c-btn--secondary" href="{{ $comparator_url }}">{{ __('comparator.open_with_these') }}</a></p>
    @endif
</div>

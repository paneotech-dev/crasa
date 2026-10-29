{{-- Exports in the current mode and the link that reproduces the view. --}}
<div class="c-cmp__share">
    <p class="c-cmp__words-label">{{ __('d.exports') }}</p>
    <p class="c-cmp__exports">
        <a class="u-btn u-btn--secondary" download data-export="csv" href="{{ route('comparator.csv', $query) }}">{{ __('d.export_csv') }}</a>
        <a class="u-btn u-btn--secondary" download data-export="xlsx" href="{{ route('comparator.xlsx', $query) }}">{{ __('d.export_xlsx') }}</a>
        <a class="u-btn u-btn--secondary" download data-export="png" href="{{ route('comparator.png', $query) }}">{{ __('d.export_png') }}</a>
        <a class="u-btn u-btn--secondary" download data-export="pdf" href="{{ route('comparator.pdf', $query) }}">{{ __('d.export_pdf') }}</a>
    </p>
    <div class="c-cmp__link">
        <label class="c-sr" for="cmp-permalink">{{ __('d.link_label') }}</label>
        <input class="u-input c-cmp__link-input" id="cmp-permalink" type="text" readonly value="{{ $comparison->permalink() }}" data-cmp-permalink>
        <button class="u-btn u-btn--primary" type="button" data-cmp-copy hidden
                data-done="{{ __('d.copied') }}" data-failed="{{ __('d.copy_failed') }}">{{ __('d.copy_link') }}</button>
    </div>
    <p class="u-help">{{ __('d.csv_note') }}</p>
</div>

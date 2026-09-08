<div class="c-filters__group">
    <span class="c-filters__legend" id="facet-{{ $key }}">{{ $legend }}</span>
    <div class="c-filters__options" role="list" aria-labelledby="facet-{{ $key }}">
        <a role="listitem" class="c-chip @if (! $active) is-active @endif"
           @if (! $active) aria-current="true" @endif
           href="{{ request()->fullUrlWithQuery([$key => null, 'page' => null]) }}">{{ __('publications.all') }}</a>
        @foreach ($options as $option)
            <a role="listitem" class="c-chip @if ((string) $active === (string) $option['value']) is-active @endif"
               @if ((string) $active === (string) $option['value']) aria-current="true" @endif
               href="{{ request()->fullUrlWithQuery([$key => $option['value'], 'page' => null]) }}">{{ $option['label'] }}</a>
        @endforeach
    </div>
</div>

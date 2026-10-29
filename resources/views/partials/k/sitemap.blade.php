{{--
    Sitemap band (Concept 3), on every public page.

    The columns are the footer menu records (back office, Menus), so the
    Secretariat edits them without a deploy. A fixed Members column
    follows: the member area, the member directory and contact. The back
    office is reached from the utility bar; the accessibility statement sits
    in the footer's bottom line, beside the terms and the privacy notice.
--}}
@php
    $nav = fn (string $path) => \App\Support\Navigation::url($path);
    $columns = \App\Support\Navigation::footer();
    $workspace = \App\Support\Workspace::for(auth()->user());
    // Concept 3 "areas" quick links. They do not fit beside the primary menu
    // at 1366 (about 200 px free) and the nav row is a drawer at 1024, so
    // they live here, linking to the filtered library and the committees, as the home pillars do.
    $areas = [
        ['label' => __('evening.area_frameworks'), 'url' => $nav('publications?type[]=guideline')],
        ['label' => __('evening.area_spectrum'), 'url' => $nav('publications?sector=telecommunications')],
        ['label' => __('evening.area_postal'), 'url' => $nav('publications?sector=postal')],
        ['label' => __('evening.area_capacity'), 'url' => $nav('committees')],
    ];
@endphp
<nav class="k-smap" data-surface-tone="dark" aria-label="{{ __('chome.sitemap') }}">
    <div class="k-wrap" style="--k-cols: {{ count($columns) + 2 }}">
        @foreach ($columns as $column)
            <div>
                <h2 class="k-smap__h">{{ $column['label'] }}</h2>
                @foreach ($column['items'] as $link)
                    <a href="{{ $link['url'] }}"
                       @if (\Illuminate\Support\Str::startsWith($link['url'], ['http://', 'https://']) && ! \Illuminate\Support\Str::startsWith($link['url'], config('app.url'))) rel="noopener" @endif>{{ $link['label'] }}</a>
                @endforeach
            </div>
        @endforeach
        <div data-k-areas>
            <h2 class="k-smap__h">{{ __('evening.areas_heading') }}</h2>
            @foreach ($areas as $area)
                <a href="{{ $area['url'] }}">{{ $area['label'] }}</a>
            @endforeach
        </div>
        <div>
            <h2 class="k-smap__h">{{ __('chome.sm_services') }}</h2>
            <a href="{{ $workspace['url'] ?? $nav(config('navigation.extranet_path')) }}">{{ $workspace['label'] ?? __('nav.extranet') }}</a>
            <a href="{{ route('directory', ['locale' => app()->getLocale()]) }}">{{ __('d3_polish.member_directory') }}</a>
            <a href="{{ $nav('contact') }}">{{ __('d3_polish.contact') }}</a>
        </div>
    </div>
</nav>

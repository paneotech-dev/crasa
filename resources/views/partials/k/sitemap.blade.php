{{--
    Sitemap band (Concept 3), on every public page: four columns, About,
    Publications, Newsroom (the footer menu records, back office, Menus,
    so the Secretariat edits them without a deploy) and a fixed Members
    column: member states, the member area, the member directory and
    contact. Those four destinations are taken out of the record columns,
    so each is linked once; a column of off-site links only (the social
    accounts) is left out, as the footer below carries the marks; and at
    most three record columns are drawn, so the band stays four. The
    back office is reached from the utility bar; the accessibility
    statement sits in the footer's bottom line.
--}}
@php
    $nav = fn (string $path) => \App\Support\Navigation::url($path);
    $workspace = \App\Support\Workspace::for(auth()->user());
    $memberAreaUrl = $workspace['url'] ?? $nav(config('navigation.extranet_path'));
    $contactUrl = $nav('contact');
    $membersUrl = $nav('members');
    $bare = fn (string $url) => rtrim(strtok($url, '?#'), '/');
    $own = array_map($bare, [$contactUrl, $membersUrl, $nav(config('navigation.extranet_path')), $memberAreaUrl]);
    $offsite = fn (string $url) => \Illuminate\Support\Str::startsWith($url, ['http://', 'https://']) && ! \Illuminate\Support\Str::startsWith($url, config('app.url'));
    $columns = collect(\App\Support\Navigation::footer())
        ->map(function (array $column) use ($bare, $own) {
            $column['items'] = array_values(array_filter($column['items'], fn ($link) => ! in_array($bare($link['url']), $own, true)));

            return $column;
        })
        ->filter(fn (array $column) => collect($column['items'])->contains(fn ($link) => ! $offsite($link['url'])))
        ->take(3)
        ->values();
@endphp
<nav class="k-smap" data-surface-tone="dark" aria-label="{{ __('chome.sitemap') }}">
    <div class="k-wrap" style="--k-cols: {{ $columns->count() + 1 }}">
        @foreach ($columns as $column)
            <div>
                <h2 class="k-smap__h">{{ $column['label'] }}</h2>
                @foreach ($column['items'] as $link)
                    <a href="{{ $link['url'] }}"
                       @if ($offsite($link['url'])) rel="noopener" @endif>{{ $link['label'] }}</a>
                @endforeach
            </div>
        @endforeach
        <div>
            <h2 class="k-smap__h">{{ __('chome.sm_services') }}</h2>
            <a href="{{ $membersUrl }}">{{ __('d3_pub.member_states') }}</a>
            <a href="{{ $memberAreaUrl }}">{{ $workspace['label'] ?? __('nav.extranet') }}</a>
            {{-- A guest is sent to sign in; a signed-in reader sees the link only where the directory opens to them. --}}
            @if (! auth()->check() || \Illuminate\Support\Facades\Gate::allows('enter-member-area'))
                <a href="{{ route('directory', ['locale' => app()->getLocale()]) }}">{{ __('d3_polish.member_directory') }}</a>
            @endif
            <a href="{{ $contactUrl }}">{{ __('d3_polish.contact') }}</a>
        </div>
    </div>
</nav>

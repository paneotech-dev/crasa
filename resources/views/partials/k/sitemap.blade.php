{{--
    Sitemap band (Concept 3), on every public page.

    The columns are the footer menu records (back office, Menus), so the
    Secretariat edits them without a deploy. A fixed Services column
    follows: the ways in and the legal pages.
--}}
@php
    $nav = fn (string $path) => \App\Support\Navigation::url($path);
    // The accessibility statement (g stream): its own localised address
    // where the controller exists, the named route otherwise.
    $a11yUrl = class_exists(\App\Http\Controllers\AccessibilityStatementController::class)
        ? \App\Http\Controllers\AccessibilityStatementController::url()
        : (Route::has('accessibility') ? route('accessibility', ['locale' => app()->getLocale()]) : null);
    $columns = \App\Support\Navigation::footer();
    $workspace = \App\Support\Workspace::for(auth()->user());
@endphp
<nav class="k-smap" data-surface-tone="dark" aria-label="{{ __('chome.sitemap') }}">
    <div class="k-wrap" style="--k-cols: {{ count($columns) + 1 }}">
        @foreach ($columns as $column)
            <div>
                <h2 class="k-smap__h">{{ $column['label'] }}</h2>
                @foreach ($column['items'] as $link)
                    <a href="{{ $link['url'] }}"
                       @if (\Illuminate\Support\Str::startsWith($link['url'], ['http://', 'https://']) && ! \Illuminate\Support\Str::startsWith($link['url'], config('app.url'))) rel="noopener" @endif>{{ $link['label'] }}</a>
                @endforeach
            </div>
        @endforeach
        <div>
            <h2 class="k-smap__h">{{ __('chome.sm_services') }}</h2>
            <a href="{{ route('admin.dashboard', ['locale' => app()->getLocale()]) }}">{{ __('nav.back_office') }}</a>
            <a href="{{ $workspace['url'] ?? $nav(config('navigation.extranet_path')) }}">{{ $workspace['label'] ?? __('nav.extranet') }}</a>
            <a href="{{ $nav('contact') }}">{{ __('chome.contact_us') }}</a>
            @if ($a11yUrl)
                <a href="{{ $a11yUrl }}">{{ __('footer.accessibility') }}</a>
            @endif
        </div>
    </div>
</nav>

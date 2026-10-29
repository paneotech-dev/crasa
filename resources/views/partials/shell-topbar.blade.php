{{--
    The top bar. 64px, white, sticky, --e-1, a sand rule beneath.

    What used to be a second row of chips -- "Interface: English",
    "System", "Help" -- is gone. The language is a pill group, the theme
    moved into account settings where it belongs, and help is an icon. Three
    labelled buttons for three things a reader touches once a month was a
    row of furniture in front of the work.

    The search field is a real GET form. It works with no script at all,
    which is the floor everything on this platform is built to.
--}}
@props(['search' => true])

@php
    $user = auth()->user();
    $locale = app()->getLocale();
    $unread = $user?->unreadNotifications()->count() ?? 0;
@endphp

<header class="a-top">
    {{-- Only below 1024px, where the sidebar is a drawer. --}}
    <button type="button" class="a-top__burger" data-shell-toggle
            aria-controls="shell-sidebar" aria-expanded="false"
            aria-label="{{ __('admin.open_navigation') }}">
        <x-shell-icon name="menu" :size="20" />
    </button>

    @if ($search)
        {{-- Back-office roles search the administration. Every other role
             searches the public site: the administration search refuses
             them, and a box that leads to a refusal is worse than none
             (presentation of 14 October 2026). --}}
        @php
            $adminSearch = $user?->can('enter-back-office') ?? false;
            $searchRoute = $adminSearch ? 'admin.search' : 'search';
        @endphp
        <form class="a-top__search" method="GET" action="{{ route($searchRoute, ['locale' => $locale]) }}" role="search">
            <label class="c-sr" for="admin-search">{{ $adminSearch ? __('admin.search_everything') : __('search.label') }}</label>
            <x-shell-icon name="search" :size="17" class="a-top__searchicon" />
            <input class="a-top__field" type="search" id="admin-search" name="q"
                   value="{{ request()->routeIs($searchRoute) ? request('q') : '' }}"
                   placeholder="{{ $adminSearch ? __('admin.search_placeholder') : __('search.label').'…' }}">
        </form>
    @else
        <span class="a-top__spacer"></span>
    @endif

    <div class="a-top__utilities">
        {{-- Mail switched off, in plain words, for the accounts that can
             switch it on. Nobody else sees it. --}}
        @if ($user?->can(\App\Enums\Permission::ManageSettings->value) && \App\Support\DeliveryCheck::mailIsSilentInProduction())
            <p class="a-top__mailoff" role="status" title="{{ __('office.mail_off_badge') }} {{ __('office.mail_off_badge_more') }}">
                <x-shell-icon name="bell" :size="15" />
                <span>{{ __('office.mail_off_badge') }}</span>
                <span class="a-top__mailoff-more">{{ __('office.mail_off_badge_more') }}</span>
            </p>
        @endif

        {{-- The language, as a pill group. Each is a link to this same page
             in that language, so switching never loses the reader's place. --}}
        <div class="a-pills" role="group" aria-label="{{ __('admin.interface_language') }}">
            @foreach (\App\Support\Locales::all() as $code => $language)
                <a @class(['a-pills__item', 'is-current' => $code === $locale])
                   href="{{ \App\Support\Locales::urlFor($code, request()) }}"
                   @if ($code === $locale) aria-current="true" @endif
                   lang="{{ $code }}">
                    <span aria-hidden="true">{{ $language['short'] }}</span>
                    <span class="c-sr">{{ $language['native'] }}</span>
                </a>
            @endforeach
        </div>

        <a class="a-top__icon" href="{{ route('notifications', ['locale' => $locale]) }}"
           aria-label="{{ trans_choice('admin.notifications_count', $unread, ['count' => $unread]) }}">
            <x-shell-icon name="bell" :size="19" />
            @if ($unread > 0)
                <span class="a-top__badge" aria-hidden="true">{{ $unread > 9 ? '9+' : $unread }}</span>
            @endif
        </a>

        {{-- The help pages are the back office's; the extranet gets a 403. --}}
        @can('enter-back-office')
            <a class="a-top__icon" href="{{ route('admin.help', ['locale' => $locale]) }}" aria-label="{{ __('help.title') }}">
                <x-shell-icon name="help" :size="19" />
            </a>
        @endcan

        {{-- The initials open the account. A menu with two items that are
             both one click away already is a menu that adds a click. --}}
        @if ($user)
            <a class="a-top__avatar" href="{{ route('account', ['locale' => $locale]) }}">
                <span aria-hidden="true">{{ \App\Support\Initials::of($user->name ?? '') }}</span>
                <span class="c-sr">{{ $user->name }}</span>
            </a>
        @endif
    </div>
</header>

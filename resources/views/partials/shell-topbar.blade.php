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
             (presentation of 14 October 2026).

             In the member area the box searches the member area, for every
             role that may enter it, and says so (INC-11): an administrator
             inside the member area was offered "Search the administration". --}}
        @php
            $inMemberArea = ($area ?? null) === 'member' || ! ($user?->can('enter-back-office') ?? false);
            $memberSearch = $inMemberArea && ($user?->can('viewAny', \App\Models\Folder::class) ?? false);
            $adminSearch = ! $inMemberArea && ($user?->can('enter-back-office') ?? false);
            $searchRoute = $memberSearch ? 'extranet.search' : ($adminSearch ? 'admin.search' : 'search');
            $searchLabel = $memberSearch ? __('office.member_search_label') : ($adminSearch ? __('admin.search_everything') : __('search.label'));
            $searchPlaceholder = $memberSearch ? __('office.member_search_label').'…' : ($adminSearch ? __('admin.search_placeholder') : __('search.label').'…');
        @endphp
        <form class="a-top__search" method="GET" action="{{ route($searchRoute, ['locale' => $locale]) }}" role="search">
            <label class="c-sr" for="admin-search">{{ $searchLabel }}</label>
            <x-shell-icon name="search" :size="17" class="a-top__searchicon" />
            <input class="a-top__field" type="search" id="admin-search" name="q"
                   value="{{ request()->routeIs($searchRoute) ? request('q') : '' }}"
                   placeholder="{{ $searchPlaceholder }}">
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

        {{-- Day 3, notify. The bell opens the latest notices as a
             disclosure: a details element, so it opens with no script, and
             every entry is a link to its item. The count is in the label
             as well as on the badge. --}}
        @if ($user)
            <details class="a-bell" data-bell>
                <summary class="a-top__icon" aria-label="{{ trans_choice('d3_notify.bell_label', $unread, ['count' => $unread]) }}">
                    <x-shell-icon name="bell" :size="19" />
                    @if ($unread > 0)
                        <span class="a-top__badge" aria-hidden="true">{{ $unread > 9 ? '9+' : $unread }}</span>
                    @endif
                </summary>
                <div class="a-bell__panel">
                    <div class="a-bell__head">
                        <h2 class="a-bell__title">{{ __('d3_notify.bell_heading') }}</h2>
                        <span class="a-bell__count">{{ trans_choice('notifications.unread_count', $unread, ['count' => $unread]) }}</span>
                    </div>
                    @include('partials.notifications.latest', ['limit' => 8])
                    <div class="a-bell__foot">
                        <a href="{{ route('notifications', ['locale' => $locale]) }}">{{ __('d3_notify.view_all') }}</a>
                        @if ($unread > 0)
                            <form method="POST" action="{{ route('notifications.read-all', ['locale' => $locale]) }}">
                                @csrf
                                <button class="n-latest__read" type="submit">{{ __('notifications.mark_all_read') }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            </details>
        @endif

        {{-- Help opens the help page for this screen, keyed by its route
             name; that page links on to the training guide for the role. --}}
        @if ($user && Route::has('screen-help') && request()->route()?->getName())
            <a class="a-top__icon" href="{{ route('screen-help', ['locale' => $locale, 'screen' => request()->route()->getName(), 'from' => request()->getRequestUri()]) }}"
               aria-label="{{ __('d3_notify.help_icon') }}">
                <x-shell-icon name="help" :size="19" />
            </a>
        @endif

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

@push('scripts')
    <script src="{{ \App\Support\Assets::url('js/bell.js') }}" defer></script>
@endpush

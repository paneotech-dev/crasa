{{--
    The sidebar, for the back office and for the extranet.

    264px, full viewport height, fixed, --c-navy. Screen 09 draws a
    horizontal section bar and it is overruled: with twenty-four
    destinations the bar wraps into a control strip two and three rows deep,
    and this is the surface the Secretariat uses every day. Disclosed
    variation, docs/OPEN_ITEMS.md.

    The group headings are tracked caps, which is the second carve-out from
    CLAUDE.md's sentence-case rule and is written into CLAUDE.md rather than
    only here. The first is the footer's column headings.

    Below 1024px this is a drawer behind a hamburger in the top bar: the
    same content in the same order, focus-trapped. With no script it is
    simply a sidebar above the content, which is why the markup is one
    element rather than two.

    Props: groups, the navigation. brand, the wordmark under the mark.
    home, where the mark points.
--}}
@props(['groups', 'brand', 'home'])

@php $user = auth()->user(); @endphp

<nav id="shell-sidebar" class="a-side" aria-label="{{ $brand }}" data-shell-sidebar>
    <a class="a-side__mark" href="{{ $home }}">
        <img src="{{ asset('img/crasa-logo.svg') }}" alt="" width="36" height="36">
        <span class="a-side__wordmark">{{ $brand }}</span>
    </a>

    {{-- The area switcher (UX-03): an administrator inside the member area
         had no way back. Only the areas this account may enter. --}}
    @if ($user)
        @php
            $locale = app()->getLocale();
            $inMember = ($area ?? null) === 'member' || ! $user->can('enter-back-office');
            $areas = array_values(array_filter([
                $user->can('enter-back-office') ? ['key' => 'admin', 'label' => __('office.area_admin'), 'url' => route('admin.dashboard', ['locale' => $locale]), 'current' => ! $inMember] : null,
                $user->can('viewAny', \App\Models\Folder::class) ? ['key' => 'member', 'label' => __('office.area_member'), 'url' => route('extranet.dashboard', ['locale' => $locale]), 'current' => $inMember] : null,
                ['key' => 'public', 'label' => __('office.area_public'), 'url' => route('home', ['locale' => $locale]), 'current' => false],
            ]));
        @endphp
        <ul class="a-side__areas" role="list" aria-label="{{ __('office.area_switcher') }}">
            @foreach ($areas as $switch)
                <li>
                    <a @class(['a-side__area', 'is-current' => $switch['current']])
                       href="{{ $switch['url'] }}" data-area="{{ $switch['key'] }}"
                       @if ($switch['current']) aria-current="true" @endif>{{ $switch['label'] }}</a>
                </li>
            @endforeach
        </ul>
    @endif

    <div class="a-side__scroll">
        @foreach ($groups as $group)
            @php
                $groupBadge = collect($group['items'])->sum(fn ($i) => (int) ($i['badge'] ?? 0));
                $groupCurrent = collect($group['items'])->contains(fn ($i) => $i['current']);
            @endphp
            {{-- Collapsible (F11). Open by default; the group holding the
                 current page cannot be closed out of sight on load. The
                 summary's badge is the sum of its rows' badges, so a closed
                 group still says something is waiting in it. --}}
            <div class="a-side__group">
                <details class="a-side__details" open data-side-group="{{ $group['key'] }}" @if ($groupCurrent) data-current @endif>
                    <summary class="a-side__summary">
                        {{-- The second sanctioned caps carve-out. See CLAUDE.md. --}}
                        <h2 class="a-side__grouplabel" id="side-group-{{ $group['key'] }}">{{ $group['label'] }}</h2>
                        @if ($groupBadge > 0)
                            <span @class(['a-side__badge', 'a-side__badge--approvals' => collect($group['items'])->contains(fn ($i) => $i['key'] === 'approvals' && ($i['badge'] ?? 0) > 0)]) aria-label="{{ trans_choice('fb.side_waiting', $groupBadge, ['count' => $groupBadge]) }}">{{ $groupBadge }}</span>
                        @endif
                        <svg class="a-side__chev" width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M4 6l4 4 4-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </summary>

                    <ul class="a-side__items" role="list" aria-labelledby="side-group-{{ $group['key'] }}">
                        @foreach ($group['items'] as $item)
                            <li>
                                <a @class(['a-side__link', 'is-current' => $item['current']])
                                   href="{{ $item['url'] }}"
                                   @if ($item['current']) aria-current="page" @endif>
                                    <x-shell-icon :name="$item['icon']" />
                                    <span>{{ $item['label'] }}</span>
                                    @if (($item['badge'] ?? 0) > 0)
                                        <span @class(['a-side__badge', 'a-side__badge--approvals' => $item['key'] === 'approvals'])><span aria-hidden="true">{{ $item['badge'] }}</span><span class="rd-sr">{{ trans_choice('fb.side_waiting', $item['badge'], ['count' => $item['badge']]) }}</span></span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </details>
            </div>
        @endforeach
    </div>

    @if ($user)
        {{-- Pinned. Who you are signed in as is the thing a shared
             workstation gets wrong, and the way out sits beside it. --}}
        <div class="a-side__me">
            <span class="a-side__avatar" aria-hidden="true">{{ \App\Support\Initials::of($user->name ?? '') }}</span>
            <span class="a-side__metext">
                <a class="a-side__name" href="{{ route('account', ['locale' => app()->getLocale()]) }}">{{ $user->name }}</a>
                <span class="a-side__role" title="{{ $user->roleLabel() }}">{{ $user->roleLabel() }}</span>
            </span>

            <form method="POST" action="{{ route('logout', ['locale' => app()->getLocale()]) }}">
                @csrf
                {{-- The word beside the icon (UX-15): an icon alone was not
                     read as the way out. --}}
                <button type="submit" class="a-side__out">
                    <x-shell-icon name="sign-out" />
                    <span>{{ __('admin.sign_out') }}</span>
                </button>
            </form>
        </div>
    @endif
</nav>

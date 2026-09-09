{{--
    Lucide, one library, as CLAUDE.md requires and as screen 04 draws them:
    18px, 1.8 stroke, currentColor, round caps. Inline rather than from a
    sprite because there are five of them and a sprite is a second request
    for 900 bytes.

    Always aria-hidden: every one sits beside its own label.
--}}
@props(['name'])

<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
     stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('folder')
            <path d="M4 20a1 1 0 01-1-1V6a1 1 0 011-1h5l2 2h8a1 1 0 011 1v11a1 1 0 01-1 1z"></path>
            @break
        @case('calendar')
            <rect x="3" y="5" width="18" height="16" rx="2"></rect>
            <path d="M3 10h18"></path><path d="M8 3v4"></path><path d="M16 3v4"></path>
            @break
        @case('bar-chart')
            <path d="M4 19V5"></path><path d="M4 19h16"></path>
            <path d="M8 19v-6"></path><path d="M13 19V9"></path><path d="M18 19v-3"></path>
            @break
        @case('grid')
            <rect x="3" y="3" width="7" height="7" rx="1"></rect>
            <rect x="14" y="3" width="7" height="7" rx="1"></rect>
            <rect x="3" y="14" width="7" height="7" rx="1"></rect>
            <rect x="14" y="14" width="7" height="7" rx="1"></rect>
            @break
        @case('search')
            <circle cx="11" cy="11" r="7"></circle><path d="M16.5 16.5L21 21"></path>
            @break
    @endswitch
</svg>

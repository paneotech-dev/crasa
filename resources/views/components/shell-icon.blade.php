{{--
    The back office and extranet icon set.

    Lucide, one library, as CLAUDE.md requires: 18px by default, 1.8
    stroke, currentColor, round caps and joins. Inline rather than from a
    sprite because a sprite is a second request for two kilobytes and the
    sidebar needs all of them on every page anyway.

    Always aria-hidden. Every one sits beside its own label, and an icon
    that repeats the label to a screen reader is an icon read twice.

    This replaces x-extranet-icon, which carried five of these for the
    extranet rail. One set, because the two shells are now the same shell.
--}}
@props(['name', 'size' => 18])

<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor"
     stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
     {{ $attributes->merge(['class' => 'a-icon']) }}>
    @switch($name)
        @case('dashboard')
            <rect x="3" y="3" width="7" height="9" rx="1"></rect>
            <rect x="14" y="3" width="7" height="5" rx="1"></rect>
            <rect x="14" y="12" width="7" height="9" rx="1"></rect>
            <rect x="3" y="16" width="7" height="5" rx="1"></rect>
            @break
        @case('audit')
            <path d="M5 3h9l5 5v13a1 1 0 01-1 1H5a1 1 0 01-1-1V4a1 1 0 011-1z"></path>
            <path d="M14 3v5h5"></path><path d="M8 13h8"></path><path d="M8 17h5"></path>
            @break
        @case('pages')
            <rect x="4" y="3" width="16" height="18" rx="2"></rect>
            <path d="M8 8h8"></path><path d="M8 12h8"></path><path d="M8 16h5"></path>
            @break
        @case('publications')
            <path d="M4 5a2 2 0 012-2h5v18H6a2 2 0 01-2-2z"></path>
            <path d="M11 3h7a2 2 0 012 2v14a2 2 0 01-2 2h-7"></path>
            @break
        @case('newsroom')
            <path d="M3 6a1 1 0 011-1h13a1 1 0 011 1v13H4a1 1 0 01-1-1z"></path>
            <path d="M18 9h2a1 1 0 011 1v8a1 1 0 01-1 1h-2"></path>
            <path d="M7 9h7"></path><path d="M7 13h7"></path>
            @break
        @case('events')
        @case('calendar')
            <rect x="3" y="5" width="18" height="16" rx="2"></rect>
            <path d="M3 10h18"></path><path d="M8 3v4"></path><path d="M16 3v4"></path>
            @break
        @case('media')
            <rect x="3" y="4" width="18" height="16" rx="2"></rect>
            <circle cx="8.5" cy="9.5" r="1.8"></circle>
            <path d="M21 16l-5-5-9 9"></path>
            @break
        @case('navigation')
            <path d="M4 6h16"></path><path d="M4 12h10"></path><path d="M4 18h7"></path>
            @break
        @case('translations')
            <path d="M4 6h9"></path><path d="M8 4v2"></path>
            <path d="M11 6c0 4-3 8-7 9"></path><path d="M6 11c1.5 2.5 3.5 4 6 5"></path>
            <path d="M13 21l4.5-11L22 21"></path><path d="M14.6 17h5.8"></path>
            @break
        @case('legal')
            <path d="M12 3v18"></path><path d="M5 7h14"></path>
            <path d="M8 7l-3.5 7h7z"></path><path d="M16 7l-3.5 7h7z"></path>
            @break
        @case('members')
            <circle cx="12" cy="12" r="9"></circle>
            <path d="M3 12h18"></path><path d="M12 3a15 15 0 010 18"></path>
            <path d="M12 3a15 15 0 000 18"></path>
            @break
        @case('authorities')
            <path d="M3 21h18"></path><path d="M5 21V10l7-5 7 5v11"></path>
            <path d="M9 21v-6h6v6"></path>
            @break
        @case('committees')
            <circle cx="9" cy="8" r="3"></circle>
            <path d="M3 20a6 6 0 0112 0"></path>
            <circle cx="17.5" cy="9.5" r="2.5"></circle>
            <path d="M16 15a5 5 0 015 5"></path>
            @break
        @case('indicators')
        @case('bar-chart')
            <path d="M4 19V5"></path><path d="M4 19h16"></path>
            <path d="M8 19v-6"></path><path d="M13 19V9"></path><path d="M18 19v-3"></path>
            @break
        @case('instruments')
            <path d="M6 3h8l5 5v13a1 1 0 01-1 1H6a1 1 0 01-1-1V4a1 1 0 011-1z"></path>
            <path d="M14 3v5h5"></path><path d="M9 15l2 2 4-4"></path>
            @break
        @case('submissions')
            <path d="M12 4v12"></path><path d="M7 9l5-5 5 5"></path>
            <path d="M4 17v2a1 1 0 001 1h14a1 1 0 001-1v-2"></path>
            @break
        @case('reports')
            <rect x="4" y="3" width="16" height="18" rx="2"></rect>
            <path d="M8 12h3v5H8z"></path><path d="M13 8h3v9h-3z"></path>
            @break
        @case('users')
            <circle cx="12" cy="8" r="3.5"></circle>
            <path d="M5 20a7 7 0 0114 0"></path>
            @break
        @case('delegates')
            <path d="M3 20a6 6 0 0112 0"></path><circle cx="9" cy="8" r="3"></circle>
            <path d="M17 10l2 2 3-3"></path>
            @break
        @case('confirmations')
            <path d="M12 3l7 3v6c0 4-3 7-7 9-4-2-7-5-7-9V6z"></path>
            <path d="M9 12l2 2 4-4"></path>
            @break
        @case('settings')
            <circle cx="12" cy="12" r="3"></circle>
            <path d="M12 2v2.5"></path><path d="M12 19.5V22"></path>
            <path d="M2 12h2.5"></path><path d="M19.5 12H22"></path>
            <path d="M5 5l1.8 1.8"></path><path d="M17.2 17.2L19 19"></path>
            <path d="M19 5l-1.8 1.8"></path><path d="M6.8 17.2L5 19"></path>
            @break
        @case('samples')
            <path d="M9 3h6"></path><path d="M10 3v6.5L5.5 18a2 2 0 001.7 3h9.6a2 2 0 001.7-3L14 9.5V3"></path>
            <path d="M7.5 15h9"></path>
            @break
        @case('folder')
            <path d="M4 20a1 1 0 01-1-1V6a1 1 0 011-1h5l2 2h8a1 1 0 011 1v11a1 1 0 01-1 1z"></path>
            @break
        @case('completeness')
        @case('grid')
            <rect x="3" y="3" width="7" height="7" rx="1"></rect>
            <rect x="14" y="3" width="7" height="7" rx="1"></rect>
            <rect x="3" y="14" width="7" height="7" rx="1"></rect>
            <rect x="14" y="14" width="7" height="7" rx="1"></rect>
            @break
        @case('comparator')
            <path d="M4 20V9"></path><path d="M10 20V4"></path>
            <path d="M16 20v-7"></path><path d="M3 20h18"></path>
            @break
        @case('search')
            <circle cx="11" cy="11" r="7"></circle><path d="M16.5 16.5L21 21"></path>
            @break
        @case('help')
            <circle cx="12" cy="12" r="9"></circle>
            <path d="M9.5 9.5a2.6 2.6 0 015 .9c0 1.7-2.5 2.1-2.5 3.6"></path>
            <path d="M12 17h.01"></path>
            @break
        @case('bell')
            <path d="M18 15V10a6 6 0 10-12 0v5l-1.5 3h15z"></path>
            <path d="M10 21h4"></path>
            @break
        @case('sign-out')
            <path d="M14 4h4a1 1 0 011 1v14a1 1 0 01-1 1h-4"></path>
            <path d="M10 8l-4 4 4 4"></path><path d="M6 12h9"></path>
            @break
        @case('menu')
            <path d="M4 7h16"></path><path d="M4 12h16"></path><path d="M4 17h16"></path>
            @break
        @case('close')
            <path d="M6 6l12 12"></path><path d="M18 6L6 18"></path>
            @break
    @endswitch
</svg>

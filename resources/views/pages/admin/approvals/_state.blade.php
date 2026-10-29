{{-- A moderation state as a word with an icon, never colour alone. Props: state. --}}
<span class="aw-state aw-state--{{ $state }}">
    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        @switch($state)
            @case('in_review')<circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2"/>@break
            @case('published')<path d="M5 12l4 4 10-10"/>@break
            @case('returned')<path d="M9 14L4 9l5-5"/><path d="M4 9h10a6 6 0 010 12h-2"/>@break
            @default<path d="M4 20h4l10-10-4-4L4 16v4z"/>
        @endswitch
    </svg>
    {{ __('fa.state_'.$state) }}
</span>

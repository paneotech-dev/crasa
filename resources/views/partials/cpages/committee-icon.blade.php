{{-- One line icon per committee, 20px grid, stroke currentColor. --}}
@php
    $paths = match ($slug ?? '') {
        'spectrum-and-frequency-management' => ['M12 20v-8', 'M8.5 12a5 5 0 017 0', 'M5.5 12a9 9 0 0113 0', 'M2.5 12a13 13 0 0119 0'],
        'postal-and-logistics' => ['M4 8l8-4 8 4v8l-8 4-8-4z', 'M4 8l8 4 8-4', 'M12 12v8'],
        'legal-and-regulatory-affairs' => ['M12 4v16', 'M7 20h10', 'M5 7h14', 'M5 7l-3 6a3 3 0 006 0z', 'M19 7l-3 6a3 3 0 006 0z'],
        'economics' => ['M4 20h16', 'M7 16v-4', 'M12 16V8', 'M17 16v-7'],
        'consumer-affairs-and-protection' => ['M12 3l7 3v5c0 4.5-3 8-7 10-4-2-7-5.5-7-10V6z', 'M9 12l2 2 4-4'],
        'human-and-institutional-capacity' => ['M9 11a3 3 0 100-6 3 3 0 000 6z', 'M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6', 'M16 5.5a3 3 0 010 5.5', 'M18 14.5c1.8.8 3 2.7 3 5'],
        default => ['M4 6h16', 'M4 12h16', 'M4 18h10'],
    };
@endphp
<svg class="cp-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">@foreach ($paths as $d)<path d="{{ $d }}"/>@endforeach</svg>

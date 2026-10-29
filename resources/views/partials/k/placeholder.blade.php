{{-- C5: the frame an item without its own photograph gets. White, with the
     CRASA mark and no label: the card's title carries the meaning, and a
     frame that says "News" reads as a picture that failed to load. --}}
<span @class(['k-ph', 'k-ph--small' => $small ?? false]) aria-hidden="true">
    <img src="{{ asset('img/crasa-logo.svg') }}" alt="" width="64" height="64" loading="lazy">
</span>

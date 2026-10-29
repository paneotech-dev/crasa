{{-- C5: the frame an item without its own photograph gets. Pale green,
     the CRASA mark and the type label in deep green. Decorative: the
     card's title carries the meaning. --}}
<span @class(['k-ph', 'k-ph--small' => $small ?? false]) aria-hidden="true">
    <img src="{{ asset('img/crasa-logo.svg') }}" alt="" width="64" height="64" loading="lazy">
    <span>{{ $label }}</span>
</span>

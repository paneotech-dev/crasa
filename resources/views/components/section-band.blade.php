{{--
    The band that opens a section.

    Screen 08 draws this exactly, and it is what the members page carries in
    the approved designs:

      <section style="background:var(--c-navy);color:var(--c-cream);
                      padding:var(--s-8) var(--s-7) var(--s-7)">
        <div style="font-size:13px;font-weight:600;letter-spacing:.14em;
                    text-transform:uppercase;color:var(--c-green-light);
                    margin-bottom:var(--s-4)">Member regulators</div>
        <h1 style="font-family:var(--f-head);font-size:clamp(32px,5.4vw,54px);
                   line-height:1.08;font-weight:600;letter-spacing:-.02em;
                   margin:0 0 var(--s-4);max-width:24ch">...</h1>
        <p style="margin:0 0 var(--s-6);font-size:19px;line-height:1.6;
                  color:var(--c-sand);max-width:70ch">...</p>
        <dl style="margin:0;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));
                   gap:var(--s-5);border-top:1px solid #2C3A55;
                   padding-top:var(--s-5);max-width:900px">
          <div><dd style="...font-size:36px...">14</dd><dt>Member regulators</dt></div>
        </dl>
      </section>

    Screen 03 draws the same band without the counts. So the counts are
    optional and everything else is fixed.

    THE PHOTOGRAPH IS TEXTURE, NOT SUBJECT. Where one is supplied it sits
    behind the band under a heavy navy overlay, so it warms the ground
    without being asked to carry meaning. That is what makes a five-image
    library adequate rather than embarrassing on an institutional platform.

    An image is passed only where it is contextually correct for the section
    it appears in. A broadcasting photograph on the postal section is a lie
    about what the reader is looking at, and a technician at a test bench
    does not illustrate a committee meeting. Where the right image does not
    exist, the navy band alone.

    The overlay is measured, not assumed: see ContrastTest. The worst case is
    a pure-white photograph beneath it, which specular highlights make a real
    case rather than a theoretical one.
--}}
@props([
    'eyebrow' => null,
    'title',
    'lead' => null,
    'counts' => [],
    'image' => null,
    'level' => 'h1',
])

@php
    // Named $photo, not $slot: Blade already owns $slot for the
    // component's own content.
    $photo = $image && \App\Support\ImageLibrary::has($image) ? $image : null;
@endphp

<section @class(['c-band', 'c-band--image' => $photo])>
    <div class="c-band__inner c-page">
        <div class="c-band__text">
            @if ($eyebrow)
                {{-- Screen 08 sets this in tracked caps. The CLAUDE.md ruling
                     excludes eyebrows and wins, as it does on the hero and the
                     hero panel. Size, weight, colour and spacing are the
                     screen's. Logged in docs/OPEN_ITEMS.md. --}}
                <p class="c-band__eyebrow">{{ $eyebrow }}</p>
            @endif

            <{{ $level }} class="c-band__title">{{ $title }}</{{ $level }}>

            @if ($lead)
                <p class="c-band__lead">{{ $lead }}</p>
            @endif

            @if ($counts !== [])
                {{-- How much is in this section, stated where the reader arrives
                     rather than discovered by scrolling. Each count links to
                     what it counts. A count of zero is shown, because zero is
                     information: an empty section that says nothing about being
                     empty reads as a section that failed to load. --}}
                <dl class="c-band__counts">
                    @foreach ($counts as $count)
                        <div>
                            <dd>
                                @if (! empty($count['url']))
                                    <a href="{{ $count['url'] }}">{{ $count['value'] }}</a>
                                @else
                                    {{ $count['value'] }}
                                @endif
                            </dd>
                            <dt>{{ $count['label'] }}</dt>
                        </div>
                    @endforeach
                </dl>
            @endif

            {{ $slot }}
        </div>

        {{-- The photograph sits in a frame beside the text, never behind
             it: no scrim, no tint, nothing laid over a picture. --}}
        @if ($photo)
            <div class="c-band__media" aria-hidden="true">
                <x-responsive-image :name="$photo" decorative sizes="(min-width: 992px) 480px, 100vw" loading="eager" class="c-img--cover" />
            </div>
        @endif
    </div>
</section>

{{--
    A page composed from its block records. There is no page-specific markup
    here by design: the arrangement is data, so an editor changes it from the
    back office rather than through a deploy.
--}}
@extends('layouts.public')

@php
    $title = $page->translate('title');
    $description = $page->translate('meta_description');
@endphp

@section('title', $title?->value ?? __('nav.home'))
@if ($description)
    @section('description', $description->value)
@endif

@section('content')
    @unless ($page->blocksRenderH1())
        {{-- The hero carries the h1 where there is one. Without a hero the
             page needs its own, or its headings start at level 2 and the
             document outline has no title.

             That h1 was a bare line of type on cream. Screen 08 draws the
             page it belongs to — the membership page — as a navy band with
             an eyebrow, the title, a standfirst and a row of counts beneath,
             so that is what a composed page opens with now.

             The counts are resolved by slug and only for the pages that have
             something to count. A page with none renders the band without
             the row rather than an empty rule. --}}
        @php
            // The eyebrow names the area and the heading describes it, as
            // screen 08 draws them. The page title is the navigation label
            // and is the fallback rather than the first choice: "Members"
            // is a menu item, not a headline. An eyebrow identical to the
            // heading is dropped rather than printed twice.
            $bandHeading = $page->translate('heading')?->value ?: ($title?->value ?? '');
            $bandEyebrow = __('nav.'.$page->slug);
            $bandEyebrow = $bandEyebrow === 'nav.'.$page->slug || $bandEyebrow === $bandHeading ? null : $bandEyebrow;
        @endphp

        <x-section-band
            :eyebrow="$bandEyebrow"
            :title="$bandHeading"
            :lead="$page->translate('standfirst')?->value"
            :counts="\App\Support\SectionCounts::forPage($page->slug)"
        />
    @endunless

    @php
        /*
         * The ground belongs to the section, not to its position. Screen 02
         * gives each one a ground for what it is, so BlockType::ground()
         * carries the value and the quotation it came from.
         *
         * One qualifier, for a run of the same type. The About page is six
         * rich-text blocks, all of which resolve to cream, and blocks.css
         * drops the leading padding between two matching grounds so they
         * fuse: the whole page became a single cream column with no seam
         * between one section and the next. A run alternates cream and
         * white so a reader can see where one ends.
         *
         * Only those two swap. A deliberate pair of darks, which is what
         * screen 02 draws under the hero, is left alone.
         */
        $previous = null;
    @endphp

    @foreach ($page->visibleBlocks as $block)
        @php
            $ground = $block->type()->ground();

            if ($ground === $previous && in_array($ground, ['cream', 'white'], true)) {
                $ground = $ground === 'cream' ? 'white' : 'cream';
            }

            $previous = $ground;
        @endphp

        <x-block :block="$block" :ground="$ground" />
    @endforeach
@endsection

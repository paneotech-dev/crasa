{{--
    The paginator, on this platform's own components.

    LARAVEL'S DEFAULT VIEW IS THE TAILWIND ONE, and its chevrons are
    <svg class="w-5 h-5"> with no width or height attribute. Tailwind is not
    on this platform and CLAUDE.md forbids introducing it, so those two
    classes resolved to nothing and each chevron expanded to fill its
    container: the audit log's pagination rendered at hundreds of pixels.
    The same class of defect as the map insets, and the same fix -- an
    inline svg states its own size in attributes and never relies on CSS
    alone to have one.

    The markup is the shape .c-pagination and .a-pager already style, so
    every listing on the platform paginates the same way.

    Every control carries a real accessible name. "Previous" and "Next"
    alone say nothing about what is being paged, and a numbered link with
    no context is a number.
--}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('pagination.label') }}" class="c-pager">
        <span class="c-pager__status" aria-live="polite">
            {{ __('pagination.showing', [
                'first' => $paginator->firstItem() ?? 0,
                'last' => $paginator->lastItem() ?? 0,
                'total' => $paginator->total(),
            ]) }}
        </span>

        <div class="c-pager__links">
            @if ($paginator->onFirstPage())
                <span class="c-pager__step is-off" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M15 6l-6 6 6 6"></path>
                    </svg>
                </span>
            @else
                <a class="c-pager__step" href="{{ $paginator->previousPageUrl() }}" rel="prev"
                   aria-label="{{ __('pagination.previous_page', ['page' => $paginator->currentPage() - 1]) }}">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M15 6l-6 6 6 6"></path>
                    </svg>
                </a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="c-pager__gap" aria-hidden="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" aria-label="{{ __('pagination.current_page', ['page' => $page]) }}">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" aria-label="{{ __('pagination.go_to_page', ['page' => $page]) }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="c-pager__step" href="{{ $paginator->nextPageUrl() }}" rel="next"
                   aria-label="{{ __('pagination.next_page', ['page' => $paginator->currentPage() + 1]) }}">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 6l6 6-6 6"></path>
                    </svg>
                </a>
            @else
                <span class="c-pager__step is-off" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 6l6 6-6 6"></path>
                    </svg>
                </span>
            @endif
        </div>
    </nav>
@endif

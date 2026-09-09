{{-- The simple paginator, on the same components. See default.blade.php
     for why Laravel's own view could not be used. --}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('pagination.label') }}" class="c-pager">
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
                </a>
            @endif
        </div>
    </nav>
@endif

{{--
    A meeting's status as the reader sees it, from Meeting::displayStatus():
    cancelled and postponed as recorded, held once it has ended, scheduled
    before that. $surface is 'admin' for the back office's a-pill and
    anything else for the public u-pill. $quiet leaves out "Scheduled" on
    public lists, where an upcoming event needs no label.
--}}
@php($st = $meeting->displayStatus())
@php($stLabel = $meeting->displayStatusLabel())
@if (($surface ?? 'public') === 'admin')
    <span class="a-pill" data-status="{{ ['cancelled' => 'rejected', 'postponed' => 'pending', 'held' => 'archived', 'scheduled' => 'validated'][$st] }}">{{ $stLabel }}</span>
@elseif ($st === 'cancelled')
    <span class="u-pill e-pill--cancelled"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>{{ __('e.status_cancelled') }}</span>
@elseif ($st === 'postponed')
    <span class="u-pill u-pill--notice"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>{{ __('e.status_postponed') }}</span>
@elseif ($st === 'held')
    <span class="u-pill u-pill--muted">{{ $stLabel }}</span>
@elseif (! ($quiet ?? false))
    <span class="u-pill u-pill--ok">{{ __('e.status_scheduled') }}</span>
@endif

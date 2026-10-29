@extends('layouts.extranet')

{{--
    Announcements as a member state reads them. Extranet item 10.

    Members only, and the model's scope is what makes that true: an
    announcement narrowed to one committee is not listed here, not linked
    and not reachable by guessing a slug for anyone without that seat.
--}}

@section('title', __('announcements.title'))
@section('note', __('announcements.note'))

@section('content')
    @if ($announcements->isEmpty())
        <div class="a-card">
            <div class="a-empty">
                <div class="a-empty__title">{{ __('announcements.none_title') }}</div>
                <div class="a-empty__body">{{ __('announcements.none_body') }}</div>
            </div>
        </div>
    @else
        <section class="a-card a-card--flush">
            <ul class="a-rows" role="list">
                @foreach ($announcements as $announcement)
                    @php($audience = collect([$announcement->sector?->name, $announcement->committee?->name])->filter())
                    <li class="a-row">
                        <span class="a-row__grow">
                            <a href="{{ route('announcements.show', ['locale' => app()->getLocale(), 'announcement' => $announcement->slug]) }}">{{ $announcement->translated('title', $announcement->slug) }}</a>
                            @if ($audience->isNotEmpty())
                                <span class="a-row__who">{{ $audience->implode(', ') }}</span>
                            @endif
                        </span>
                        <span class="a-row__time">
                            <time datetime="{{ $announcement->published_at?->toDateString() }}">{{ $announcement->published_at?->displayed()->isoFormat('D MMMM YYYY') }}</time>
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>

        {{ $announcements->links() }}
    @endif
@endsection

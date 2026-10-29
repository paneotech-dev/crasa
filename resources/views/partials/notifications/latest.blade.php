{{--
    The latest notifications of the signed-in reader, self-contained: it
    reads them itself, so any surface can place it (the bell in the top bar,
    a dashboard column). Each entry links to its item through
    notifications.open, which marks it read on the way; an unread entry has
    its own Mark as read button.

    @include('partials.notifications.latest', ['limit' => 5])
--}}
@php
    $latestLimit = max(1, min(50, (int) ($limit ?? 8)));
    $latestUser = auth()->user();
    $latest = $latestUser ? $latestUser->notifications()->limit($latestLimit)->get() : collect();
    $latestLocale = app()->getLocale();
@endphp

@if ($latest->isEmpty())
    <p class="n-latest__empty">{{ __('d3_notify.bell_empty') }}</p>
@else
    <ul class="n-latest" role="list">
        @foreach ($latest as $item)
            @php($itemTitle = \App\Support\Notifier::titleOf($item))
            <li @class(['n-latest__item', 'is-unread' => $item->read_at === null])>
                <a class="n-latest__link" href="{{ route('notifications.open', ['locale' => $latestLocale, 'notification' => $item->id]) }}">{{ $itemTitle }}</a>
                <span class="n-latest__meta">
                    {{ __('d3_notify.category_'.\App\Support\Notifier::categoryOf($item->data)) }},
                    <time datetime="{{ $item->created_at?->toIso8601String() }}">{{ $item->created_at?->displayed()->isoFormat('D MMM YYYY, HH:mm') }}</time>
                </span>
                @if ($item->read_at === null)
                    <span class="n-latest__state">
                        <span class="a-pill" data-status="pending">{{ __('notifications.unread') }}</span>
                        <form method="POST" action="{{ route('notifications.read', ['locale' => $latestLocale, 'notification' => $item->id]) }}">
                            @csrf
                            <button class="n-latest__read" type="submit" aria-label="{{ __('d3_notify.mark_read_named', ['title' => \Illuminate\Support\Str::limit($itemTitle, 80)]) }}">{{ __('d3_notify.mark_read') }}</button>
                        </form>
                    </span>
                @endif
            </li>
        @endforeach
    </ul>
@endif

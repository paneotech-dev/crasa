{{--
    The digest email. Plain text through Laravel's markdown-free path: a
    regulator's mail client may be anything, and a list of links is a list
    of links.
--}}
@php($locale = $locale ?? 'en')
<p>{{ __('notifications.digest_intro', [], $locale) }}</p>

<ul>
    @foreach ($items as $item)
        <li>
            <a href="{{ route('notifications.open', ['locale' => $locale, 'notification' => $item->id]) }}">{{ $item->data['title'] ?? __('notifications.untitled', [], $locale) }}</a>
            &mdash; {{ __('notifications.trigger_'.($item->data['type'] ?? 'announcement'), [], $locale) }}
        </li>
    @endforeach
</ul>

<p>
    <a href="{{ route('notifications', ['locale' => $locale]) }}">{{ __('notifications.digest_action', [], $locale) }}</a>
</p>

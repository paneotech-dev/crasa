{{--
    Secretariat notices as a list (Day 3, 8c). $items: rows from
    App\Support\MemberHome::notice(). Each row: the type badge, the title,
    a two-line summary, the attachments, and the read state in words, with
    "New" on a recent unread notice. $compact drops the attachments and the
    read word on the home page, where the list is three rows long.
--}}
<ul class="mh-notices" role="list">
    @foreach ($items as $n)
        @php($a = $n['model'])
        <li @class(['mh-notice', 'is-unread' => ! $n['read']])>
            <div class="mh-notice__top">
                <span class="mh-type" data-type="{{ $n['type'] }}">{{ $n['type_label'] }}</span>
                @if ($n['new'])<span class="a-pill" data-status="pending">{{ __('d3_look.new') }}</span>@endif
                <time class="mh-meta" datetime="{{ $a->published_at?->toDateString() }}">{{ $a->published_at?->displayed()->isoFormat('D MMMM YYYY') }}</time>
            </div>
            <h3 class="mh-notice__title">
                <a href="{{ route('announcements.show', ['locale' => app()->getLocale(), 'announcement' => $a->slug]) }}">{{ $a->translated('title', $a->slug) }}</a>
            </h3>
            @if ($n['summary'] !== '')
                <p class="mh-notice__summary">{{ $n['summary'] }}</p>
            @endif
            @if (! ($compact ?? false))
                <div class="mh-notice__foot">
                    @if ($n['attachments']->isNotEmpty())
                        <ul class="mh-attach" role="list" aria-label="{{ trans_choice('d3_look.attachments', $n['attachments']->count(), ['count' => $n['attachments']->count()]) }}">
                            @foreach ($n['attachments'] as $doc)
                                <li><a href="{{ route('documents.show', ['locale' => app()->getLocale(), 'document' => $doc->id]) }}">{{ $doc->original_filename }}</a> <span class="mh-meta">{{ strtoupper(pathinfo($doc->original_filename, PATHINFO_EXTENSION)) }}, {{ $doc->readableSize() }}</span></li>
                            @endforeach
                        </ul>
                    @else
                        <span class="mh-meta">{{ __('d3_look.no_attachments') }}</span>
                    @endif
                    <span class="mh-read" data-read="{{ $n['read'] ? 'true' : 'false' }}">{{ $n['read'] ? __('d3_look.read') : __('d3_look.unread') }}</span>
                </div>
            @endif
        </li>
    @endforeach
</ul>

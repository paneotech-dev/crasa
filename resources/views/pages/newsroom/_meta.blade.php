{{-- Type, date and sector on one line. Plain news carries no type badge:
     the section already says news. --}}
<p class="n-meta">
    @if ($item->categorySlug() !== 'news')<span class="n-badge n-badge--{{ str_replace('_', '-', $item->categorySlug()) }}" @if ($item->category) style="{{ $item->category->badgeStyle() }}" @endif>{{ $item->categoryLabel() }}</span>@endif
    <span class="n-meta__date">{{ $item->date_unknown ? __('events.date_not_stated') : \App\Support\NewsFormat::longDate($item->published_at) }}</span>
    @if ($item->sector)<span class="n-meta__sector">{{ $item->sector->name }}</span>@endif
</p>

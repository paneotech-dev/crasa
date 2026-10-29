{{-- Type badge, date and sector on one line, separated by a middle dot. --}}
<p class="n-meta">
    <span class="n-badge n-badge--{{ str_replace('_', '-', $item->categorySlug()) }}" @if ($item->category) style="{{ $item->category->badgeStyle() }}" @endif>{{ $item->categoryLabel() }}</span>
    <span class="n-meta__date">{{ $item->date_unknown ? __('events.date_not_stated') : \App\Support\NewsFormat::longDate($item->published_at) }}</span>
    @if ($item->sector)<span class="n-meta__sector">{{ $item->sector->name }}</span>@endif
</p>

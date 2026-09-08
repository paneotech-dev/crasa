{{--
    A filtered document list. Every download goes through the controller;
    there is no direct filesystem URL anywhere in this markup.
--}}
@php
    $heading = $block->translate('heading');
    $documents = $documents ?? collect();
@endphp

<section class="b b--tight" @if ($block->anchor) id="{{ $block->anchor }}" @endif @if (! empty($ground)) data-ground="{{ $ground }}" @endif>
    <div class="b__inner">
        @if ($heading)
            <h2 class="b__heading">{{ $heading->value }}</h2>
        @endif

        @if ($documents->isEmpty())
            @include('blocks._empty', [
                'title' => __('blocks.documents_empty_title'),
                'body' => __('blocks.documents_empty_body'),
            ])
        @else
            <div class="b-rows">
                @foreach ($documents as $document)
                    <div class="b-row">
                        <div class="b-row__main">
                            <div class="b-row__title">{{ $document->original_filename }}</div>
                            <div class="b-row__meta">
                                {{ strtoupper(pathinfo($document->original_filename, PATHINFO_EXTENSION)) }},
                                {{ $document->readableSize() }}
                                <span class="c-lang-badge">{{ strtoupper($document->locale) }}</span>
                            </div>
                        </div>
                        <div class="b-row__actions">
                            <a class="c-btn c-btn--secondary" href="{{ route('documents.download', ['document' => $document->id]) }}">
                                {{ __('blocks.download') }}
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>

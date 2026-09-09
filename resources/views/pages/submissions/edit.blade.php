@extends('layouts.admin')

@section('title', __('submissions.form_title', ['period' => $submission->period]))
@section('note', $submission->country?->name)

@section('actions')
    @if ($editable)
        <a class="a-btn a-btn--secondary" href="{{ route('submissions.import', ['locale' => app()->getLocale(), 'submission' => $submission->id]) }}">{{ __('import.title') }}</a>
    @endif
    <a class="a-btn a-btn--secondary" href="{{ route('submissions.export.csv', ['locale' => app()->getLocale(), 'submission' => $submission->id]) }}">{{ __('import.export_csv') }}</a>
@endsection

@section('crumbs')
    <li><a href="{{ route('submissions', ['locale' => app()->getLocale()]) }}">{{ __('submissions.title') }}</a></li>
    <li aria-current="page">{{ $submission->period }}</li>
@endsection

@section('content')
    {{-- Screen 06 draws all four states across the top, so a representative
         sees where their return sits without reading a word out of a table.
         Numbered because this genuinely is a sequence. --}}
    <section class="a-panel">
        <div class="a-panel__head">
            <h2>{{ __('submissions.workflow') }}</h2>
        </div>

        <ol class="s-stages">
            @foreach (\App\Enums\SubmissionStatus::cases() as $stage)
                <li class="s-stage"
                    @if ($submission->status === $stage) aria-current="step" @endif
                    data-state="{{ $stage->value }}">
                    <span class="s-stage__name">{{ __($stage->labelKey()) }}</span>
                    <span class="s-stage__sub">{{ __('submissions.stage_'.$stage->value.'_sub') }}</span>
                </li>
            @endforeach
        </ol>

        <div class="a-panel__foot">
            <strong>{{ __('submissions.banner_'.$submission->status->value.'_title') }}</strong>
            {{ __('submissions.banner_'.$submission->status->value.'_body') }}
        </div>
    </section>

    {{-- Screen 06: returned submissions carry their comments at the top, in
         red, against the specific field each one is about. --}}
    @if ($submission->status === \App\Enums\SubmissionStatus::Returned && $comments->isNotEmpty())
        <section class="s-returned">
            <h2>{{ trans_choice('submissions.returned_title', $comments->count(), ['count' => $comments->count()]) }}</h2>
            <p>{{ __('submissions.returned_body') }}</p>

            <div class="s-comments">
                @foreach ($comments as $comment)
                    <div class="s-comment">
                        <div class="s-comment__main">
                            <div class="s-comment__field">{{ $comment->indicator?->name }}</div>
                            <div class="s-comment__text">{{ $comment->comment }}</div>
                        </div>
                        <div class="s-comment__who">
                            {{ $comment->author?->name ?? __('admin.system') }}<br>
                            {{ $comment->created_at->isoFormat('D MMMM YYYY, HH:mm') }}
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <div class="a-split">
        <section class="a-resource a-split__main" style="flex:999 1 520px">
            <div class="a-resource__head">
                <h2 class="a-resource__title">{{ __('submissions.eform') }}</h2>
                <div class="a-resource__note">{{ __('submissions.eform_note', [
                    'indicators' => \App\Models\Indicator::active()->count(),
                    'categories' => $categories->count(),
                ]) }}</div>
            </div>

            <form method="POST" action="{{ route('submissions.update', ['locale' => app()->getLocale(), 'submission' => $submission->id]) }}">
                @csrf
                @method('PUT')

                @foreach ($categories as $category => $indicators)
                    <fieldset class="s-category">
                        <legend>{{ __('comparator.category_'.$category) }}</legend>

                        {{-- Screen 06: grid-template-columns:repeat(2,minmax(0,1fr));
                             gap:var(--s-5) --}}
                        <div class="s-fields">
                            @foreach ($indicators as $indicator)
                                @php
                                    $value = $values->get($indicator->id);
                                    $comment = $comments->get($indicator->id);
                                    $numeric = in_array($indicator->data_type, ['numeric', 'percentage', 'currency', 'year'], true);
                                @endphp
                                <div class="s-field" @if ($comment) data-flagged @endif>
                                    <label class="s-field__label" for="v-{{ $indicator->id }}">{{ $indicator->name }}</label>

                                    @if ($indicator->data_type === 'boolean')
                                        <div class="s-field__choices" role="group" aria-labelledby="v-{{ $indicator->id }}-label">
                                            <span id="v-{{ $indicator->id }}-label" class="c-sr">{{ $indicator->name }}</span>
                                            @foreach ([1 => __('comparator.yes'), 0 => __('comparator.no')] as $option => $label)
                                                <label class="s-choice">
                                                    <input type="radio" name="values[{{ $indicator->id }}][value]" value="{{ $option }}"
                                                           @checked((string) ($value?->value_numeric ?? '') === (string) $option)
                                                           @disabled(! $editable)>
                                                    {{ $label }}
                                                </label>
                                            @endforeach
                                        </div>
                                    @else
                                        <input class="s-field__input"
                                               id="v-{{ $indicator->id }}"
                                               type="{{ $numeric ? 'text' : 'text' }}"
                                               inputmode="{{ $numeric ? 'decimal' : 'text' }}"
                                               name="values[{{ $indicator->id }}][value]"
                                               value="{{ old('values.'.$indicator->id.'.value', $numeric ? ($value?->value_numeric !== null ? rtrim(rtrim((string) $value->value_numeric, '0'), '.') : '') : $value?->value_text) }}"
                                               @disabled(! $editable)>
                                    @endif

                                    <p class="s-field__hint">{{ $indicator->definition ?: $indicator->unitLabel() }}</p>

                                    {{-- Screen 06 puts a source field under every
                                         figure and marks it red when it is empty:
                                         "Source note required". --}}
                                    <input class="s-field__source"
                                           type="text"
                                           name="values[{{ $indicator->id }}][source_note]"
                                           value="{{ old('values.'.$indicator->id.'.source_note', $value?->source_note) }}"
                                           placeholder="{{ __('submissions.source_required') }}"
                                           aria-label="{{ __('submissions.source_for', ['field' => $indicator->name]) }}"
                                           @if ($value?->hasFigure() && blank($value?->source_note)) data-required @endif
                                           @disabled(! $editable)>

                                    @if ($comment)
                                        <p class="s-field__comment">{{ $comment->comment }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach

                @if ($editable)
                    <div class="a-form__actions" style="padding:var(--s-5)">
                        <button type="submit" class="a-btn a-btn--secondary">{{ __('submissions.save') }}</button>
                    </div>
                @endif
            </form>

            @if ($editable)
                <form method="POST" action="{{ route('submissions.submit', ['locale' => app()->getLocale(), 'submission' => $submission->id]) }}"
                      style="padding:0 var(--s-5) var(--s-5)">
                    @csrf
                    <p class="a-hint">{{ __('submissions.submit_hint') }}</p>
                    <button type="submit" class="a-btn a-btn--primary">{{ __('submissions.submit_action') }}</button>
                </form>
            @endif
        </section>

        <aside class="a-split__side" style="flex:1 1 300px">
            {{-- Screen 06: "Progress by category" beside the form. --}}
            <div class="a-panel">
                <div class="a-panel__head"><h2>{{ __('submissions.progress') }}</h2></div>
                @foreach ($submission->progress() as $category => $counts)
                    <div style="margin-bottom:var(--s-4)">
                        <div class="a-track__label">
                            <b>{{ __('comparator.category_'.$category) }}</b>
                            <span>{{ $counts['filled'] }} / {{ $counts['total'] }}</span>
                        </div>
                        <div class="a-track">
                            <span class="a-track__seg" data-sector="ict" style="width:{{ $counts['total'] ? round($counts['filled'] / $counts['total'] * 100) : 0 }}%"></span>
                        </div>
                    </div>
                @endforeach
                <p class="a-hint">{{ __('submissions.progress_note') }}</p>
            </div>

            {{-- The trail. Screen 06 draws a version history panel; this is
                 the return's own transitions, which is what an auditor asks
                 for and what a general log makes hard to reconstruct. --}}
            <div class="a-panel">
                <div class="a-panel__head"><h2>{{ __('submissions.history') }}</h2></div>
                @if ($submission->events->isEmpty())
                    <div class="a-empty">
                        <div class="a-empty__title">{{ __('submissions.history_empty_title') }}</div>
                        <div class="a-empty__body">{{ __('submissions.history_empty_body') }}</div>
                    </div>
                @else
                    <div class="a-rows">
                        @foreach ($submission->events as $event)
                            <div class="a-row">
                                <span class="a-row__time">{{ $event->created_at?->isoFormat('D MMM, HH:mm') }}</span>
                                <span class="a-row__grow">{{ __('submissions.event_'.$event->to_status) }}</span>
                                <span class="a-row__who">{{ $event->actor?->name ?? __('admin.system') }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </aside>
    </div>
@endsection

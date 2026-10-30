@extends('layouts.admin')

@section('title', __('submissions.review_title', ['country' => $submission->country?->displayName(), 'period' => $submission->period]))
@section('note', __('submissions.review_note'))

@section('crumbs')
    <li><a href="{{ route('admin.submissions', ['locale' => app()->getLocale()]) }}">{{ __('submissions.queue_title') }}</a></li>
    <li aria-current="page">{{ $submission->country?->displayName() }} {{ $submission->period }}</li>
@endsection

@section('content')
    <section class="a-panel">
        <div class="a-panel__head">
            <h2>{{ __('submissions.workflow') }}</h2>
            <span class="a-panel__meta">
                <span class="a-pill" data-status="{{ $submission->status->value }}">{{ __($submission->status->labelKey()) }}</span>
            </span>
        </div>
        <div class="a-panel__foot">
            <strong>{{ __('submissions.banner_'.$submission->status->value.'_title') }}</strong>
            {{ __('submissions.banner_'.$submission->status->value.'_body') }}
        </div>
    </section>

    {{-- The figures, read only. The Secretariat reviews a return; it does
         not rewrite one, or the member state's name would end up on figures
         they did not enter. --}}
    <section class="a-resource">
        <div class="a-resource__head">
            <h2 class="a-resource__title">{{ __('submissions.figures') }}</h2>
            <div class="a-resource__note">{{ __('submissions.figures_note') }}</div>
        </div>

        <div class="a-tablewrap">
            <table class="a-table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('admin.col_field') }}</th>
                        <th scope="col" data-numeric>{{ __('comparator.col_value') }}</th>
                        <th scope="col">{{ __('submissions.source') }}</th>
                        <th scope="col">{{ __('submissions.comment') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($categories as $category => $indicators)
                        @foreach ($indicators as $indicator)
                            @php
                                $value = $values->get($indicator->id);
                                $existing = $submission->comments->firstWhere('indicator_id', $indicator->id);
                            @endphp
                            <tr>
                                <th scope="row">
                                    {{ $indicator->displayName() }}
                                    <span class="a-table__sub">{{ __('comparator.category_'.$category) }}</span>
                                </th>
                                <td data-numeric @unless ($value?->hasFigure()) data-quiet @endunless>
                                    {{ $value?->hasFigure()
                                        ? ($value->value_numeric !== null ? rtrim(rtrim((string) $value->value_numeric, '0'), '.') : $value->value_text)
                                        : __('comparator.not_reported') }}
                                </td>
                                <td data-quiet>
                                    @if ($value?->hasFigure() && blank($value->source_note))
                                        <span class="a-altstate" data-state="missing">{{ __('submissions.source_required') }}</span>
                                    @else
                                        {{ $value?->source_note }}
                                    @endif
                                </td>
                                <td>
                                    @if ($existing)
                                        <span class="a-altstate" data-state="{{ $existing->resolved ? 'complete' : 'partial' }}">{{ $existing->comment }}</span>
                                    @elseif ($canReview)
                                        <form method="POST" action="{{ route('admin.submissions.comment', ['locale' => app()->getLocale(), 'submission' => $submission->id]) }}">
                                            @csrf
                                            <input type="hidden" name="indicator_id" value="{{ $indicator->id }}">
                                            <label class="c-sr" for="c-{{ $indicator->id }}">{{ __('submissions.comment_on', ['field' => $indicator->displayName()]) }}</label>
                                            <input class="a-input" id="c-{{ $indicator->id }}" name="comment" type="text" placeholder="{{ __('submissions.comment_placeholder') }}">
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if ($canReview)
        <section class="a-panel">
            <div class="a-panel__head">
                <h2>{{ __('submissions.decision') }}</h2>
                <span class="a-panel__meta">{{ __('submissions.decision_note') }}</span>
            </div>

            <form class="a-form" method="POST" action="{{ route('admin.submissions.decide', ['locale' => app()->getLocale(), 'submission' => $submission->id]) }}">
                @csrf
                <div class="a-field">
                    <label class="a-label" for="note">{{ __('submissions.review_note_label') }}</label>
                    <textarea class="a-input" id="note" name="note" rows="4" maxlength="2000"></textarea>
                    <span class="a-hint">{{ __('submissions.review_note_hint') }}</span>
                </div>
                <div class="a-form__actions">
                    <button type="submit" class="a-btn a-btn--primary" name="status" value="validated">{{ __('submissions.validate_action') }}</button>
                    <button type="submit" class="a-btn a-btn--danger" name="status" value="returned">{{ __('submissions.return_action') }}</button>
                </div>
            </form>
        </section>
    @endif

    <section class="a-panel">
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
                        <span class="a-row__time">{{ $event->created_at?->displayed()->isoFormat('D MMM, HH:mm') }}</span>
                        <span class="a-row__grow">
                            {{ __('submissions.event_'.$event->to_status) }}
                            @if ($event->note)<span class="a-crow__where" style="display:block">{{ $event->note }}</span>@endif
                        </span>
                        <span class="a-row__who">{{ $event->actor?->name ?? __('admin.system') }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
@endsection

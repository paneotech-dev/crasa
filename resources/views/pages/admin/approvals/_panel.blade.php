{{--
    The approval panel on an item's edit screen (F2). Props: record.
    Shows the state, the moderator's comment on a returned item, and
    "Send for approval". The server re-checks everything.
--}}
@php
    $aType = \App\Support\Approvals::typeOf($record);
    $aUser = auth()->user();
@endphp
@if ($aType !== null && $record->exists)
    @php
        $aState = \App\Support\Approvals::state($record);
        $aLatest = \App\Support\Approvals::latest($record);
        $aNeeds = \App\Support\Approvals::requiresApproval($aType);
        $aLocale = app()->getLocale();
        // Day 3: an editor saves and sends with the form's two buttons.
        $aFlow = \App\Support\EditorFlow::applies($aUser, $record);
        $aRevision = $aFlow ? \App\Support\EditorFlow::pendingFor($record) : null;
    @endphp
    <section class="a-panel aw-panel" aria-labelledby="aw-panel-title" data-approval-state="{{ $aState }}">
        <div class="a-panel__head">
            <h2 id="aw-panel-title">{{ __('fa.panel_title') }}</h2>
            @include('pages.admin.approvals._state', ['state' => $aState])
        </div>

        <p class="a-hint">{{ $aNeeds ? __('fa.panel_needs_approval') : __('fa.panel_no_approval') }}</p>

        @error('approval')
            <p class="a-notice a-notice--error" role="alert">{{ $message }}</p>
        @enderror

        @if ($aRevision !== null)
            <p class="a-notice a-notice--pending m3-revision" data-revision-state="{{ $aRevision->state }}">
                <strong>{{ __('m3_edflow.revision_label') }}.</strong>
                {{ __(match ($aRevision->state) { 'in_review' => 'm3_edflow.revision_waiting', 'returned' => 'm3_edflow.revision_returned', default => 'm3_edflow.revision_draft' }) }}
            </p>
        @endif

        @if ($aState === 'in_review')
            <p>{{ __('fa.panel_waiting', ['name' => $aLatest?->submitter?->name ?? __('fa.former_item'), 'date' => $aLatest?->submitted_at?->displayed()->isoFormat('D MMMM YYYY, HH:mm')]) }}</p>
            <p class="a-notice a-notice--pending" data-approval-locked>{{ __('evening.locked_notice') }}</p>
            @can('view', $aLatest)
                <a class="a-btn a-btn--secondary" href="{{ route('admin.approvals.show', ['locale' => $aLocale, 'approval' => $aLatest->id]) }}">{{ __('fa.panel_open_decision') }}</a>
            @endcan
            @if (\App\Support\Approvals::mayWithdraw($aUser, $aLatest))
                @include('pages.admin.approvals._withdraw', ['approval' => $aLatest, 'title' => \App\Support\Approvals::title($record)])
            @endif
        @elseif ($aState === 'published')
            <p>{{ __('fa.panel_published_help') }}</p>
            @if ($aRevision?->state === 'returned')
                <div class="aw-comment">
                    <p><strong>{{ __('fa.panel_returned', ['name' => $aRevision->decider?->name ?? '', 'date' => $aRevision->decided_at?->displayed()->isoFormat('D MMMM YYYY, HH:mm')]) }}</strong></p>
                    <blockquote>{{ $aRevision->comment }}</blockquote>
                </div>
            @endif
        @else
            @if ($aState === 'returned' && $aLatest)
                <div class="aw-comment">
                    <p><strong>{{ __('fa.panel_returned', ['name' => $aLatest->decider?->name ?? '', 'date' => $aLatest->decided_at?->displayed()->isoFormat('D MMMM YYYY, HH:mm')]) }}</strong></p>
                    <blockquote>{{ $aLatest->comment }}</blockquote>
                    <p class="a-hint">{{ __('fa.panel_returned_help') }}</p>
                </div>
            @endif
            @if ($aNeeds && ! $aFlow && \App\Support\Approvals::maySubmit($aUser, $record))
                <form class="a-form" method="POST" action="{{ route('admin.approvals.submit', ['locale' => $aLocale, 'type' => $aType, 'id' => $record->getKey()]) }}">
                    @csrf
                    <div class="a-field">
                        <label class="a-label" for="aw-note">{{ __('fa.panel_note') }}</label>
                        <textarea class="a-input" id="aw-note" name="note" rows="2" aria-describedby="aw-note-help">{{ old('note') }}</textarea>
                        <span class="a-hint" id="aw-note-help">{{ __('fa.panel_note_help') }}</span>
                    </div>
                    <div class="a-form__actions">
                        <button type="submit" class="a-btn a-btn--primary" data-send-for-approval>{{ __('fa.panel_send') }}</button>
                    </div>
                </form>
            @endif
        @endif
    </section>
@endif

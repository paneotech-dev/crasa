{{--
    Day 3, editor flow: the two buttons an editor saves with, in place of a
    status. Props: cancel (url, optional). "Save draft" comes first so the
    Enter key never sends anything to a moderator. The server decides what
    each does; see App\Support\EditorFlow.
--}}
<div class="a-field m3-note">
    <label class="a-label" for="m3-note">{{ __('m3_edflow.note_label') }}</label>
    <textarea class="a-input" id="m3-note" name="note" rows="2" aria-describedby="m3-note-help">{{ old('note') }}</textarea>
    <span class="a-hint" id="m3-note-help">{{ __('m3_edflow.note_help') }}</span>
</div>
<p class="a-hint" id="m3-actions-help">{{ __('m3_edflow.actions_help') }}</p>
<div class="a-form__actions">
    <button type="submit" name="intent" value="draft" class="a-btn a-btn--secondary" data-save-draft aria-describedby="m3-actions-help">{{ __('m3_edflow.save_draft') }}</button>
    <button type="submit" name="intent" value="send" class="a-btn a-btn--primary" data-save-send aria-describedby="m3-actions-help">{{ __('m3_edflow.save_send') }}</button>
    @isset($cancel)
        <a href="{{ $cancel }}">{{ __('admin.cancel') }}</a>
    @endisset
</div>

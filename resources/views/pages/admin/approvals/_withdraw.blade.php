{{-- Withdraw an item in review to Draft (administrators). Props: approval, title. --}}
<section class="a-panel aw-decide" aria-labelledby="aw-withdraw-h-{{ $approval->id }}">
    <div class="a-panel__head"><h2 id="aw-withdraw-h-{{ $approval->id }}">{{ __('evening.withdraw_title') }}</h2></div>
    <p class="a-hint" id="aw-withdraw-help-{{ $approval->id }}">{{ __('evening.withdraw_help') }}</p>
    <form class="a-form" method="POST" action="{{ route('admin.approvals.withdraw', ['locale' => app()->getLocale(), 'approval' => $approval->id]) }}">
        @csrf @method('PUT')
        <div class="a-field">
            <label class="a-label" for="withdraw-comment-{{ $approval->id }}">{{ __('evening.withdraw_comment') }}</label>
            <textarea class="a-input" id="withdraw-comment-{{ $approval->id }}" name="comment" rows="2" aria-describedby="aw-withdraw-help-{{ $approval->id }}"></textarea>
        </div>
        <button type="submit" class="a-btn a-btn--secondary" data-withdraw>{{ __('evening.withdraw_button') }}<span class="c-sr">: {{ $title }}</span></button>
    </form>
</section>

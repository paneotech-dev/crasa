{{--
    Day 3 (item 1a): the moderator's notice at the top of the dashboard,
    while anything waits for their decision. Not dismissable: it goes away
    when the inbox is empty. The count is the sidebar badge's count.
--}}
@php $m3Waiting = \App\Support\Approvals::awaitingCountFor(auth()->user()); @endphp
@if ($m3Waiting > 0)
    <section class="a-notice a-notice--pending m3-inbox" aria-labelledby="m3-inbox-title" data-approval-inbox-notice>
        <div class="m3-inbox__text">
            <h2 id="m3-inbox-title" class="m3-inbox__title">{{ __('m3_edflow.inbox_title') }}</h2>
            <p>{{ trans_choice('m3_edflow.inbox_body', $m3Waiting, ['count' => $m3Waiting]) }}</p>
        </div>
        <a class="a-btn a-btn--primary" href="{{ route('admin.approvals', ['locale' => app()->getLocale()]) }}">{{ __('m3_edflow.inbox_open') }}</a>
    </section>
@endif

{{-- The centre serves both surfaces, so it takes whichever shell the
     reader's own workspace uses. A second copy of this page for the
     extranet would be a second copy to keep in step. --}}
@extends(\App\Support\Workspace::isBackOffice(auth()->user()) ? 'layouts.admin' : 'layouts.extranet')

{{--
    The notification centre, RP-05 and extranet item 6.

    Two things on one page, because they are the same question asked twice:
    what have I been told, and what do I want to be told about. A reader who
    finds forty notices they did not want should be able to turn them off
    from the page they found them on.

    The triggers are a closed set. Every one is about regulatory content: an
    instrument, a return, a document, a meeting's existence, a comment on a
    paper, a notice from the Secretariat. Nothing about a journey or a room
    appears here, and adding one would need a new case in the enum.
--}}

@section('title', __('notifications.title'))
@section('note', __('notifications.note'))

@section('actions')
    @if ($unread > 0)
        <form method="POST" action="{{ route('notifications.read-all', ['locale' => app()->getLocale()]) }}">
            @csrf
            <button class="a-btn a-btn--secondary" type="submit">{{ __('notifications.mark_all_read') }}</button>
        </form>
    @endif
@endsection

@section('content')
    {{-- Day 3: filter by type and by read state. A plain GET form. --}}
    <form class="a-filters n-filters" method="GET" action="{{ route('notifications', ['locale' => app()->getLocale()]) }}">
        <div class="a-field">
            <label class="a-label" for="filter-type">{{ __('d3_notify.filter_type') }}</label>
            <select class="a-select" id="filter-type" name="type">
                <option value="">{{ __('d3_notify.filter_all_types') }}</option>
                @foreach ($categories as $category)
                    <option value="{{ $category }}" @selected($type === $category)>{{ __('d3_notify.category_'.$category) }}</option>
                @endforeach
            </select>
        </div>
        <div class="a-field">
            <label class="a-label" for="filter-state">{{ __('d3_notify.filter_state') }}</label>
            <select class="a-select" id="filter-state" name="state">
                <option value="">{{ __('d3_notify.state_all') }}</option>
                <option value="unread" @selected($state === 'unread')>{{ __('d3_notify.state_unread') }}</option>
                <option value="read" @selected($state === 'read')>{{ __('d3_notify.state_read') }}</option>
            </select>
        </div>
        <button class="a-btn a-btn--secondary" type="submit">{{ __('d3_notify.apply_filters') }}</button>
    </form>

    <section class="a-card a-card--flush">
        <div class="a-card__head">
            <h2>{{ __('notifications.recent') }}</h2>
            <span class="a-card__meta">{{ trans_choice('notifications.unread_count', $unread, ['count' => $unread]) }}</span>
        </div>

        @if ($notifications->isEmpty())
            <div class="a-empty">
                @if ($type !== null || $state !== null)
                    <div class="a-empty__title">{{ __('d3_notify.filtered_empty_title') }}</div>
                    <div class="a-empty__body">{{ __('d3_notify.filtered_empty_body') }}</div>
                @else
                    <div class="a-empty__title">{{ __('notifications.empty_title') }}</div>
                    <div class="a-empty__body">{{ __('notifications.empty_body') }}</div>
                @endif
            </div>
        @else
            <ul class="a-rows n-rows" role="list">
                @foreach ($notifications as $notification)
                    @php($rowTitle = \App\Support\Notifier::titleOf($notification))
                    <li @class(['a-row', 'is-unread' => $notification->read_at === null])>
                        <span class="a-row__grow">
                            <a href="{{ route('notifications.open', ['locale' => app()->getLocale(), 'notification' => $notification->id]) }}">
                                {{ $rowTitle }}
                            </a>
                            <span class="a-row__who">
                                {{ __('d3_notify.category_'.\App\Support\Notifier::categoryOf($notification->data)) }}
                            </span>
                        </span>
                        <span class="a-row__time">
                            <time datetime="{{ $notification->created_at?->toIso8601String() }}">{{ $notification->created_at?->displayed()->isoFormat('D MMMM YYYY, HH:mm') }}</time>
                        </span>
                        {{-- Read state is a word as well as a weight. An
                             unread row told apart only by a heavier font is
                             a distinction carried by one visual channel. --}}
                        @if ($notification->read_at === null)
                            <span class="a-pill" data-status="pending">{{ __('notifications.unread') }}</span>
                            <form class="n-rowform" method="POST" action="{{ route('notifications.read', ['locale' => app()->getLocale(), 'notification' => $notification->id]) }}">
                                @csrf
                                <button class="n-latest__read" type="submit" aria-label="{{ __('d3_notify.mark_read_named', ['title' => \Illuminate\Support\Str::limit($rowTitle, 80)]) }}">{{ __('d3_notify.mark_read') }}</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{ $notifications->links() }}

    <section class="a-card">
        <div class="a-card__head">
            <h2 id="preferences">{{ __('notifications.preferences') }}</h2>
        </div>

        <form class="a-form" method="POST" action="{{ route('notifications.preferences', ['locale' => app()->getLocale()]) }}">
            @csrf
            @method('PUT')

            <fieldset class="a-fieldset">
                <legend class="a-legend">{{ __('notifications.tell_me_about') }}</legend>

                @foreach ($triggers as $trigger)
                    <div class="a-check">
                        <input type="checkbox" id="trigger_{{ $trigger->value }}" name="triggers[]"
                               value="{{ $trigger->value }}" @checked(in_array($trigger->value, $chosen, true))>
                        <span class="a-check__text">
                            <label class="a-check__label" for="trigger_{{ $trigger->value }}">{{ __($trigger->labelKey()) }}</label>
                            <span class="a-hint">{{ __($trigger->descriptionKey()) }}</span>
                        </span>
                    </div>
                @endforeach
            </fieldset>

            <fieldset class="a-fieldset">
                <legend class="a-legend">{{ __('notifications.by_email') }}</legend>
                <p class="a-hint">{{ __('notifications.email_note') }}</p>

                <div class="a-field">
                    <label class="a-label" for="digest_frequency">{{ __('notifications.digest_label') }}</label>
                    <select class="a-select" id="digest_frequency" name="digest_frequency">
                        @foreach ($frequencies as $option)
                            <option value="{{ $option->value }}" @selected($frequency === $option->value)>{{ __($option->labelKey()) }}</option>
                        @endforeach
                    </select>
                </div>
            </fieldset>

            <div class="a-form__actions">
                <button class="a-btn a-btn--primary" type="submit">{{ __('notifications.save_preferences') }}</button>
            </div>
        </form>
    </section>
@endsection

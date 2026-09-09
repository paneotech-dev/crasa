{{--
    The offline state, screen 15.

      Available offline
      Retry connection
      Manage saved items
      What is cached, and what is not

    The screen states the policy in its own words and this page repeats it,
    because a reader who is offline on a shared device is entitled to know
    what is on that device. "Never cached: documents graded Confidential or
    Secretariat" is a promise, and a promise is worth stating where the
    person affected can read it.

    Not a browser error. A browser error tells a delegate the site is broken;
    this tells them what they can still do.
--}}
@extends('layouts.public')

@section('title', __('app.offline_title'))

@section('content')
    <x-section-band
        :eyebrow="__('app.offline_eyebrow')"
        :title="__('app.offline_title')"
        :lead="__('app.offline_lead')"
    />

    <div class="c-section">
        <div class="c-page c-page--reading">
            <p>
                <button class="c-btn c-btn--primary" type="button" data-offline-retry>{{ __('app.retry') }}</button>
            </p>

            <h2>{{ __('app.saved_title') }}</h2>
            <p class="c-lead">{{ __('app.saved_lead') }}</p>

            {{-- Filled by the service worker. With no script, or no worker,
                 the empty state below stands and is the truth: nothing has
                 been saved because nothing could be. --}}
            <ul class="c-saved" role="list" data-saved-list>
                <li class="c-saved__empty" data-saved-empty>{{ __('app.saved_none') }}</li>
            </ul>

            <h2>{{ __('app.policy_title') }}</h2>

            <dl class="c-meta c-policy">
                <div>
                    <dt>{{ __('app.policy_cached') }}</dt>
                    <dd>{{ __('app.policy_cached_body') }}</dd>
                </div>
                <div>
                    <dt>{{ __('app.policy_never') }}</dt>
                    <dd>{{ __('app.policy_never_body') }}</dd>
                </div>
                <div>
                    <dt>{{ __('app.policy_stale') }}</dt>
                    <dd>{{ __('app.policy_stale_body') }}</dd>
                </div>
            </dl>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/offline.js') }}" defer></script>
@endpush

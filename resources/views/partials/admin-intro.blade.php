{{--
    The introduction, shown until the account dismisses it.

    IT USED TO BE THE FIRST THING ON THE DASHBOARD AND THE HEIGHT OF THE
    VIEWPORT: a heading, a paragraph and four numbered steps, above every
    figure the page carries. Ruled down on 10 September 2026 to a card at
    the foot, two lines, linking to the help page. Somebody who opens the
    dashboard every morning does not need the tour above the work, and the
    tour is at the help page permanently either way.

    Still not a modal. That pattern traps focus, is almost impossible to
    operate with a screen reader, breaks when the layout reflows, and cannot
    be re-read once dismissed.

    Dismissal is per account rather than per session: this one is a fact
    about the reader rather than about the platform's state, and somebody
    who has taken the tour has taken it.
--}}
@php $user = auth()->user(); @endphp

@if ($user && ! $user->hasDismissed(\App\Support\Onboarding::WELCOME))
    <section class="a-alert a-intro" role="note" aria-labelledby="intro-heading">
        <form class="a-alert__dismissform" method="POST" action="{{ route('admin.help.dismiss', ['locale' => app()->getLocale()]) }}">
            @csrf
            <input type="hidden" name="hint" value="{{ \App\Support\Onboarding::WELCOME }}">
            <button type="submit" class="a-alert__dismiss" aria-label="{{ __('help.dismiss') }}">
                <x-shell-icon name="close" :size="16" />
            </button>
        </form>

        <strong id="intro-heading">{{ __('help.welcome_title', ['name' => explode(' ', $user->name)[0]]) }}</strong>
        {{ __('help.welcome_body') }}
        <a href="{{ route('admin.help', ['locale' => app()->getLocale()]) }}">{{ __('admin.dash_welcome_action') }}</a>
    </section>
@endif

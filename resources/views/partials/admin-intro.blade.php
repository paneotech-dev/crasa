{{--
    The introduction, shown on the dashboard until the account dismisses it.

    Deliberately not a modal walkthrough. That pattern traps focus, is
    almost impossible to operate with a screen reader, breaks when the
    layout reflows, and cannot be re-read once dismissed. This is a panel on
    the page it describes, it can be left open, and everything in it stays
    at the help page permanently.
--}}
@php $user = auth()->user(); @endphp

@if ($user && ! $user->hasDismissed(\App\Support\Onboarding::WELCOME))
    <section class="a-panel a-intro" aria-labelledby="intro-heading">
        <div class="a-panel__head">
            <h2 id="intro-heading">{{ __('help.welcome_title', ['name' => \App\Support\Initials::of($user->name) ? explode(' ', $user->name)[0] : $user->name]) }}</h2>
            <form method="POST" action="{{ route('admin.help.dismiss', ['locale' => app()->getLocale()]) }}">
                @csrf
                <input type="hidden" name="hint" value="{{ \App\Support\Onboarding::WELCOME }}">
                <button type="submit" class="a-linkbutton">{{ __('help.dismiss') }}</button>
            </form>
        </div>

        <p class="a-hint" style="max-width:72ch">{{ __('help.welcome_body') }}</p>

        <ol>
            @foreach (\App\Support\Onboarding::steps() as $step)
                <li>
                    <span class="a-intro__text">
                        <b><a href="{{ $step['url'] }}">{{ $step['title'] }}</a></b>
                        <span>{{ $step['body'] }}</span>
                    </span>
                </li>
            @endforeach
        </ol>

        <div class="a-panel__foot">
            {{ __('help.welcome_foot') }}
            <a href="{{ route('admin.help', ['locale' => app()->getLocale()]) }}">{{ __('help.open_help') }}</a>
        </div>
    </section>
@endif

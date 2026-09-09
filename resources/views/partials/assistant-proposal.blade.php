{{--
    A PROPOSAL, NOT A FEATURE.

    CLAUDE.md reserves conversational features for a separate commercial
    addendum and forbids building them in this codebase. This does not build
    one. There is no input, no textarea and no field of any kind here or
    anywhere else on the platform; nothing on this page can be typed into,
    and nothing here makes a request to anybody.

    What it does is ask a question the Secretariat cannot otherwise answer:
    would member states use this. The only thing a reader can do is say yes
    once, which writes one row and one audit entry.

    Its audience is member state representatives and committee members. The
    Secretariat's own staff are not asked, because a count that includes the
    people proposing it is a count of the wrong thing.

    THE FLOOR. Without script the dialog is an ordinary section of the page
    and the button that opens it is not drawn: see .a-modal in admin.css.
    Nothing here depends on JavaScript to be readable.

    Removable in one commit. docs/OPEN_ITEMS.md.
--}}
@php
    $user = auth()->user();
    $audience = $user !== null && in_array($user->role, [
        \App\Enums\Role::MemberStateRep,
        \App\Enums\Role::CommitteeMember,
    ], true);
    $noted = \App\Models\FeatureInterest::recorded($user, \App\Models\FeatureInterest::ASSISTANT);
@endphp

@if ($audience)
    <section class="a-card a-proposal">
        <div class="a-proposal__head">
            <span class="a-proposal__mark" aria-hidden="true">
                <x-shell-icon name="assistant" :size="20" />
            </span>
            <div>
                <h2 class="a-proposal__title">{{ __('assistant.title') }}</h2>
                <p class="a-proposal__label">{{ __('assistant.label') }}</p>
            </div>
        </div>

        <p class="a-proposal__framing">{{ __('assistant.framing') }}</p>

        <div class="a-proposal__actions">
            <button type="button" class="a-btn a-btn--secondary" data-modal-open="assistant-panel">
                {{ __('assistant.card_action') }}
            </button>
        </div>
    </section>

    <dialog id="assistant-panel" class="a-modal" aria-labelledby="assistant-panel-title">
        <div class="a-modal__inner">
            <div class="a-proposal__head">
                <span class="a-proposal__mark" aria-hidden="true">
                    <x-shell-icon name="assistant" :size="20" />
                </span>
                <div>
                    <h2 class="a-proposal__title" id="assistant-panel-title">{{ __('assistant.title') }}</h2>
                    <p class="a-proposal__label">{{ __('assistant.label') }}</p>
                </div>
                <button type="button" class="a-modal__close" data-modal-close aria-label="{{ __('assistant.close') }}">
                    <x-shell-icon name="close" :size="18" />
                </button>
            </div>

            <p class="a-proposal__framing">{{ __('assistant.framing') }}</p>

            <h3 class="a-proposal__subhead">{{ __('assistant.capabilities') }}</h3>

            <ul class="a-proposal__list" role="list">
                @foreach ([
                    ['compare', 'scales'],
                    ['gap', 'gap'],
                    ['draft', 'draft'],
                    ['language', 'translations'],
                    ['return', 'submissions'],
                ] as [$key, $icon])
                    <li class="a-proposal__item">
                        <span class="a-proposal__itemmark" aria-hidden="true">
                            <x-shell-icon :name="$icon" :size="18" />
                        </span>
                        <span>
                            <span class="a-proposal__itemtitle">{{ __('assistant.'.$key.'_title') }}</span>
                            {{-- The examples are questions somebody would ask,
                                 quoted. The fourth is a description rather
                                 than a question because what it describes is
                                 the answer's language, not the question's. --}}
                            <span class="a-proposal__example">
                                @if ($key === 'language')
                                    {{ __('assistant.language_example') }}
                                @else
                                    &ldquo;{{ __('assistant.'.$key.'_example') }}&rdquo;
                                @endif
                            </span>
                        </span>
                    </li>
                @endforeach
            </ul>

            <h3 class="a-proposal__subhead">{{ __('assistant.how') }}</h3>

            <ul class="a-proposal__how" role="list">
                <li>{{ __('assistant.how_sources') }}</li>
                <li>{{ __('assistant.how_cites') }}</li>
                <li>{{ __('assistant.how_clearance') }}</li>
            </ul>

            <p class="a-proposal__status">{{ __('assistant.status') }}</p>

            <div class="a-proposal__panelactions">
                @if ($noted)
                    <span class="a-proposal__noted">{{ __('assistant.noted') }}</span>
                @else
                    <form method="POST" action="{{ route('extranet.interest', ['locale' => app()->getLocale()]) }}">
                        @csrf
                        <input type="hidden" name="feature" value="{{ \App\Models\FeatureInterest::ASSISTANT }}">
                        <button type="submit" class="a-btn a-btn--secondary">{{ __('assistant.interest') }}</button>
                    </form>
                @endif

                <button type="button" class="a-linkbutton" data-modal-close>{{ __('assistant.close') }}</button>
            </div>
        </div>
    </dialog>
@endif

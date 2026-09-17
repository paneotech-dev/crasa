{{--
    What the layout says before the page does.

    The mail warning first and never dismissable: mail that goes nowhere is
    the one misconfiguration on this platform that produces no error
    anywhere, and the alternative is finding out at a workshop when somebody
    asks why they never received the notification.

    Shown to the accounts that can change the setting. A member state
    representative can do nothing about ".env", and on the workshop
    projector the sentence reads as a platform fault.
--}}
@php($canActOnMail = auth()->user()?->can(\App\Enums\Permission::ManageSettings->value) ?? false)
@if ($canActOnMail && \App\Support\DeliveryCheck::mailIsSilentInProduction())
    <div class="a-alert a-alert--error" role="alert">
        <strong>{{ __('admin.mail_silent_title') }}</strong>
        {{ __('admin.mail_silent_body', ['driver' => config('mail.default'), 'env' => app()->environment()]) }}
    </div>
@elseif ($canActOnMail && \App\Support\DeliveryCheck::fromAddressIsUnset())
    <div class="a-alert a-alert--pending" role="alert">
        <strong>{{ __('admin.mail_from_title') }}</strong>
        {{ __('admin.mail_from_body', ['address' => config('mail.from.address')]) }}
    </div>
@endif

@if (session('status'))
    <div class="a-alert a-alert--success" role="status">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="a-alert a-alert--error" role="alert">
        <ul style="margin:0;padding-left:1.2em">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{--
    What the layout says before the page does.

    Mail switched off is no longer a banner here: it is a small badge in the
    top bar, shown to administrators only (shell-topbar). A red banner on
    every screen read as a platform fault on the workshop projector, and
    members and editors can do nothing about it.

    The placeholder sender address is still flagged here, to the accounts
    that can change it, because it only shows once mail is switched on.
--}}
@php($canActOnMail = auth()->user()?->can(\App\Enums\Permission::ManageSettings->value) ?? false)
@if ($canActOnMail && ! \App\Support\DeliveryCheck::mailIsSilent() && \App\Support\DeliveryCheck::fromAddressIsUnset())
    <div class="a-alert a-alert--pending" role="alert">
        <strong>{{ __('admin.mail_from_title') }}</strong>
        {{ __('admin.mail_from_body', ['address' => config('mail.from.address')]) }}
    </div>
@endif

@if (session('expired'))
    <div class="a-alert a-alert--error" role="alert">{{ session('expired') }}</div>
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

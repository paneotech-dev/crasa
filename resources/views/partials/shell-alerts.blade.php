{{--
    What the layout says before the page does.

    The mail warning first and never dismissable: mail that goes nowhere is
    the one misconfiguration on this platform that produces no error
    anywhere, and the alternative is finding out at a workshop when somebody
    asks why they never received the notification.
--}}
@if (\App\Support\DeliveryCheck::mailIsSilentInProduction())
    <div class="a-alert a-alert--error" role="alert">
        <strong>{{ __('admin.mail_silent_title') }}</strong>
        {{ __('admin.mail_silent_body', ['driver' => config('mail.default'), 'env' => app()->environment()]) }}
    </div>
@elseif (\App\Support\DeliveryCheck::fromAddressIsUnset())
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

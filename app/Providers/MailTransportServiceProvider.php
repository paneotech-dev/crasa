<?php

namespace App\Providers;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

/**
 * The Brevo transport (MAIL_MAILER=brevo), sent through Brevo's HTTP API.
 *
 * Laravel has no Brevo driver of its own, so the Symfony bridge is
 * registered as a custom transport. The key comes from
 * config('services.brevo.key') (BREVO_API_KEY) and is never logged. The
 * transport is only built when the brevo mailer is first used, so a
 * platform on the log mailer never reads the key at all.
 */
class MailTransportServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Mail::extend('brevo', function (array $config = []) {
            return (new BrevoTransportFactory)->create(
                new Dsn('brevo+api', 'default', $config['key'] ?? config('services.brevo.key'))
            );
        });
    }
}

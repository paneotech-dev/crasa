<?php

namespace App\Providers;

use App\Auth\IdentityProvider;
use App\Auth\PlatformIdentityProvider;
use App\Search\DatabaseSearchDriver;
use App\Search\SearchDriver;
use App\Support\DeliveryCheck;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /*
         * The identity provider. IN-01.
         *
         * Bound to this platform's own user store until Form 3 arrives
         * with the Extranet and EMP platform details. A SAML 2.0 or OAuth
         * 2.0 implementation is bound here instead and nothing else in the
         * application changes: see docs/SSO_REQUIREMENTS.md for what CRASA
         * must supply.
         */
        $this->app->bind(
            IdentityProvider::class,
            fn () => new PlatformIdentityProvider
        );

        /*
         * The search driver, bound behind its interface.
         *
         * ElasticSearch cannot run on this host, so MariaDB FULLTEXT ships.
         * Moving to a managed service later is a new class bound here, not a
         * rewrite of the controller or the views.
         */
        $this->app->bind(
            SearchDriver::class,
            fn () => match (config('crasa.search.driver')) {
                default => new DatabaseSearchDriver,
            }
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * Say so if mail is going nowhere.
         *
         * MAIL_MAILER=log accepts every message and delivers none, and does
         * it without an exception, a failed job or a visible error. Written
         * once per boot at error level so it is in the log a host or a
         * reviewer actually reads, and shown on every back office screen.
         * See docs/MAIL_SETUP.md.
         */
        DeliveryCheck::report();

        // Six enquiries an hour per address. Enough that a regulator sending
        // two in a morning is never stopped; few enough that a script is.
        RateLimiter::for('enquiries', fn (Request $request) => Limit::perHour(6)
            ->by($request->ip())
            // back() with an explicit fallback: a request with no referer,
            // which a privacy-conscious browser or a proxy can strip, would
            // otherwise land on the site root rather than the form.
            ->response(fn () => back(fallback: route('contact', ['locale' => app()->getLocale()]))
                ->withInput($request->except('website'))
                ->withErrors(['message' => __('contact.throttled')])));

        //
    }
}

<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use App\Auth\IdentityProvider;
use App\Auth\PlatformIdentityProvider;
use App\Search\DatabaseSearchDriver;
use App\Search\SearchDriver;
use App\Support\DeliveryCheck;
use App\Support\PublicCache;
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

        /*
         * The paginator uses this platform's own view.
         *
         * Laravel's default is pagination::tailwind, whose chevrons are
         * <svg class="w-5 h-5"> -- two Tailwind classes on a platform
         * CLAUDE.md forbids Tailwind on. They resolved to nothing and each
         * chevron expanded to fill its container: the audit log's
         * pagination rendered at hundreds of pixels.
         *
         * Publishing the view is not enough on its own; the default has to
         * be pointed at it.
         */
        Paginator::defaultView('pagination::default');

        /*
         * The public figures cache forgets everything when a record it is
         * built from is saved or deleted, so an editor sees a change on the
         * homepage at once rather than ten minutes later.
         */
        foreach ([
            \App\Models\Authority::class, \App\Models\Committee::class, \App\Models\Country::class,
            \App\Models\Document::class, \App\Models\Indicator::class, \App\Models\IndicatorValue::class,
            \App\Models\Meeting::class, \App\Models\NewsItem::class, \App\Models\Page::class,
            \App\Models\PageBlock::class, \App\Models\Publication::class, \App\Models\Sector::class,
            \App\Models\Translation::class,
        ] as $model) {
            $model::saved(fn () => PublicCache::flush());
            $model::deleted(fn () => PublicCache::flush());
        }
        Paginator::defaultSimpleView('pagination::simple-default');

        /*
         * One named limiter per public write path (19 October 2026).
         *
         * These routes used to carry anonymous throttle:N,M middleware, and
         * for a signed-out reader Laravel keys every anonymous limit on the
         * same domain-and-address string: six failed sign-ins from a
         * workshop's Wi-Fi then used up the password reset allowance of the
         * whole room, and a few registrations blocked the next language's
         * form. A named limiter's counter is prefixed with its own name, so
         * no path can spend another's allowance.
         *
         * The per-address figures are set for a room of a hundred people on
         * one address. The real protection against guessing is per account:
         * LoginController locks an account after ten failures, wherever they
         * come from, and the two-factor challenge after five wrong codes.
         */
        RateLimiter::for('sign-in', fn (Request $request) => Limit::perMinutes(10, 600)->by($request->ip()));
        RateLimiter::for('password-email', fn (Request $request) => [
            Limit::perHour(60)->by($request->ip()),
            // One address cannot be flooded with reset mails.
            Limit::perHour(5)->by('to:'.strtolower((string) $request->input('email'))),
        ]);
        RateLimiter::for('password-update', fn (Request $request) => Limit::perHour(60)->by($request->ip()));
        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinutes(10, 60)->by($request->ip()));
        RateLimiter::for('sso-callback', fn (Request $request) => Limit::perMinutes(10, 60)->by($request->ip()));
        RateLimiter::for('registration', fn (Request $request) => Limit::perHour(60)->by($request->ip()));
        RateLimiter::for('feature-interest', fn (Request $request) => Limit::perHour(20)->by((string) ($request->user()?->id ?? $request->ip())));
        RateLimiter::for('search-suggest', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));

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

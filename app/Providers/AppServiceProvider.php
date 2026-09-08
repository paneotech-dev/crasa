<?php

namespace App\Providers;

use App\Search\DatabaseSearchDriver;
use App\Search\SearchDriver;
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

<?php

namespace App\Providers;

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
        //
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

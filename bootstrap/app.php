<?php

use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureTwoFactorEnrolled;
use App\Http\Middleware\SecurityHeaders;
use App\Support\Locales;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Both run on the whole web group, so neither can be forgotten on a
        // route added later. A suspended account reaches only the page
        // telling the holder who to contact, and an administrator who has
        // not confirmed two-factor enrolment reaches only enrolment.
        /*
         * Where an unauthenticated request is sent. Every route is under a
         * {locale} prefix, so route('login') without one throws
         * UrlGenerationException and the guard returns 500 instead of a
         * redirect — a check that silently is not one.
         *
         * The locale comes from the URL being requested, so a reader who
         * hits a Portuguese page is sent to the Portuguese login.
         */
        $middleware->redirectGuestsTo(function (Request $request) {
            $segment = $request->segment(1);

            return route('login', [
                'locale' => Locales::isSupported($segment)
                    ? $segment
                    : Locales::FALLBACK,
            ]);
        });

        $middleware->web(append: [
            // First, so every response carries them including the ones the
            // other two short-circuit.
            SecurityHeaders::class,
            EnsureAccountActive::class,
            EnsureTwoFactorEnrolled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

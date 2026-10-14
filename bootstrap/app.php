<?php

use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureTwoFactorEnrolled;
use App\Http\Middleware\SecurityHeaders;
use App\Support\Locales;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpKernel\Exception\HttpException;

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

        /*
         * Error pages in the reader's language. An address that matches no
         * route never reaches SetLocale, so the language is taken from the
         * first segment here, before any error view renders, and route
         * helpers get the same default the routed pages have.
         */
        $exceptions->render(function (Throwable $e, Request $request) {
            $segment = $request->segment(1);
            $locale = Locales::isSupported($segment) ? $segment : Locales::FALLBACK;
            app()->setLocale($locale);
            URL::defaults(['locale' => $locale]);

            return null;
        });

        /*
         * An expired form (419). The reader is sent back to the form they
         * were filling in, with what they typed (never a password) and a
         * notice saying the session expired and to try again, instead of a
         * page that only says "Page Expired".
         */
        // Laravel has already turned TokenMismatchException into an HTTP 419 here.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419 || $request->expectsJson()) {
                return null;
            }

            $home = url('/'.app()->getLocale());
            $previous = (string) ($request->headers->get('referer') ?: ($request->hasSession() ? $request->session()->previousUrl() : ''));
            $sameSite = str_starts_with($previous, url('/').'/') || (str_starts_with($previous, '/') && ! str_starts_with($previous, '//'));
            $back = $sameSite && ! $request->is('*/logout') ? $previous : $home;

            return redirect()->to($back)
                ->withInput($request->except(['password', 'password_confirmation', 'current_password', '_token']))
                ->with('expired', __('errors.expired_notice'));
        });
    })->create();

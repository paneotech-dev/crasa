<?php

use App\Http\Controllers\LocaleRedirectController;
use App\Http\Middleware\SetLocale;
use App\Support\Locales;
use Illuminate\Support\Facades\Route;

/*
 * Every public page lives under a language segment: /en, /fr, /pt. The root
 * carries no language and redirects to the one the reader last chose, or the
 * one their browser asks for, or English.
 */

Route::get('/', LocaleRedirectController::class)->name('locale.redirect');

Route::prefix('{locale}')
    ->whereIn('locale', Locales::SUPPORTED)
    ->middleware(SetLocale::class)
    ->group(function () {
        Route::view('/', 'pages.home')->name('home');

        /*
         * Redirect target for EnsureTwoFactorEnrolled. The enrolment flow
         * itself is screen 13 and is not built in this task, so this is a
         * bare placeholder rather than a screen. It exists so the redirect
         * is real and testable, and so an unenrolled administrator is not
         * bounced to a missing route.
         */
        /*
         * Where a suspended account lands. Ruling 3. The real page arrives
         * with the account screens in stage 8; this is the route target so
         * the redirect is real and cannot loop.
         */
        Route::get('account/suspended', fn () => response(
            'This account is suspended. Contact the CRASA Secretariat to restore access.',
            403
        ))->name('account.suspended');

        Route::get('two-factor/enrol', fn () => response(
            'Two-factor enrolment is required for this account. The enrolment flow is not built yet.',
            501
        ))->name('two-factor.enrol');
    });

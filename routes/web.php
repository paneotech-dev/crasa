<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\LocaleRedirectController;
use App\Http\Controllers\NewsroomController;
use App\Http\Controllers\PageController;
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
        Route::get('/', [PageController::class, 'home'])->name('home');

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

        /*
         * Composed public pages: About, Committees, Membership. Each is a
         * page record with blocks, so the Secretariat edits them from the
         * back office rather than through a deploy.
         */
        Route::get('about', [PageController::class, 'show'])->defaults('slug', 'about')->name('about');
        Route::get('committees', [PageController::class, 'show'])->defaults('slug', 'committees')->name('committees');
        Route::get('members', [PageController::class, 'show'])->defaults('slug', 'members')->name('members');

        /*
         * Newsroom. WS-06.
         */
        Route::get('newsroom', [NewsroomController::class, 'index'])->name('newsroom');
        Route::get('newsroom/{slug}', [NewsroomController::class, 'show'])->name('newsroom.show');

        /*
         * Contact. The rate limit is per IP and deliberately loose: six an
         * hour stops a script without stopping a regulator who sends two
         * enquiries in a morning. No CAPTCHA, which WCAG 2.1 AA and
         * CLAUDE.md both point away from.
         */
        Route::get('contact', [ContactController::class, 'show'])->name('contact');
        Route::post('contact', [ContactController::class, 'store'])
            ->middleware('throttle:enquiries')
            ->name('contact.store');

        /*
         * Documents are never served from the filesystem. Every file goes
         * through the controller, which checks role, country scope and
         * clearance before streaming a byte, and writes an audit row for
         * anything that is not public.
         */
        Route::get('documents/{document}', [DocumentController::class, 'show'])
            ->name('documents.show');
        Route::get('documents/{document}/download', [DocumentController::class, 'download'])
            ->name('documents.download');

        Route::get('two-factor/enrol', fn () => response(
            'Two-factor enrolment is required for this account. The enrolment flow is not built yet.',
            501
        ))->name('two-factor.enrol');
    });

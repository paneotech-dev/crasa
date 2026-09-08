<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\UserAdminController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Auth\TwoFactorEnrolmentController;
use App\Http\Controllers\CompletenessController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\LocaleRedirectController;
use App\Http\Controllers\NewsroomController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PublicationController;
use App\Http\Controllers\SearchController;
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
         * Legal pages. Linked from the footer on every page, so they must
         * not 404.
         */
        /*
         * Drawn in the screen 02 footer; the feature itself is a later
         * phase. See PublicPagesSeeder::awaitingFeature().
         */
        Route::get('comparator', [PageController::class, 'show'])->defaults('slug', 'comparator')->name('comparator');
        Route::get('events', [PageController::class, 'show'])->defaults('slug', 'events')->name('events');

        Route::get('privacy', [PageController::class, 'show'])->defaults('slug', 'privacy')->name('privacy');
        Route::get('terms', [PageController::class, 'show'])->defaults('slug', 'terms')->name('terms');
        Route::get('accessibility', [PageController::class, 'show'])->defaults('slug', 'accessibility')->name('accessibility');

        /*
         * The extranet is phase 3. Its entry point is the sign-in page,
         * which is where the header action and the footer link both go
         * rather than to a 404.
         */
        Route::get('extranet', fn () => redirect()->route('login', ['locale' => app()->getLocale()]))
            ->name('extranet');

        /*
         * Search. WS-04. Behind a driver interface, so a managed
         * ElasticSearch service later is a config change.
         */
        Route::get('search', [SearchController::class, 'index'])->name('search');
        Route::get('search/suggest', [SearchController::class, 'suggest'])
            ->middleware('throttle:60,1')->name('search.suggest');

        /*
         * The regulatory library. WS-05.
         */
        Route::get('publications', [PublicationController::class, 'index'])->name('publications');
        Route::get('publications/{slug}', [PublicationController::class, 'show'])->name('publications.show');

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

        /*
         * Authentication. Screens 11 and 13.
         *
         * The guest routes carry the guest middleware so a signed-in reader
         * is not offered a login form, and both throttles are keyed on the
         * address and the IP together so one attacker cannot lock out a
         * known account by guessing at it.
         */
        Route::middleware('guest')->group(function () {
            Route::get('login', [LoginController::class, 'show'])->name('login');
            Route::post('login', [LoginController::class, 'store'])->name('login.store');

            Route::get('password/forgotten', [PasswordResetController::class, 'request'])->name('password.request');
            Route::post('password/forgotten', [PasswordResetController::class, 'email'])
                ->middleware('throttle:6,60')->name('password.email');
            Route::get('password/reset/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
            Route::post('password/reset', [PasswordResetController::class, 'update'])->name('password.update');

            Route::get('two-factor/challenge', [TwoFactorChallengeController::class, 'show'])
                ->name('two-factor.challenge');
            Route::post('two-factor/challenge', [TwoFactorChallengeController::class, 'store'])
                ->name('two-factor.challenge.store');
        });

        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

        Route::middleware('auth')->group(function () {
            /*
             * The completeness matrix. BUILD_SPEC 6.1: visible to the
             * Secretariat and to members, which is any signed-in account
             * that can read published indicators.
             */
            Route::get('completeness', [CompletenessController::class, 'index'])->name('completeness');

            Route::get('account', [AccountController::class, 'show'])->name('account');
            Route::put('account', [AccountController::class, 'update'])->name('account.update');
            Route::put('account/password', [AccountController::class, 'updatePassword'])->name('account.password');

            Route::get('two-factor/enrol', [TwoFactorEnrolmentController::class, 'show'])->name('two-factor.enrol');
            Route::post('two-factor/confirm', [TwoFactorEnrolmentController::class, 'confirm'])->name('two-factor.confirm');
            Route::post('two-factor/recovery-codes', [TwoFactorEnrolmentController::class, 'regenerate'])->name('two-factor.regenerate');
            Route::delete('two-factor', [TwoFactorEnrolmentController::class, 'destroy'])->name('two-factor.destroy');

            /*
             * Administration. Every action re-checks the policy; the
             * navigation is not the access control.
             */
            Route::prefix('admin')->name('admin.')->group(function () {
                Route::get('users', [UserAdminController::class, 'index'])->name('users');
                Route::get('users/invite', [UserAdminController::class, 'form'])->name('users.form');
                Route::post('users/invite', [UserAdminController::class, 'invite'])->name('users.invite');
                Route::put('users/{user}/role', [UserAdminController::class, 'assignRole'])->name('users.role');
                Route::put('users/{user}/suspend', [UserAdminController::class, 'suspend'])->name('users.suspend');
                Route::put('users/{user}/reactivate', [UserAdminController::class, 'reactivate'])->name('users.reactivate');
                Route::get('audit', [AuditLogController::class, 'index'])->name('audit');
            });
        });

    });

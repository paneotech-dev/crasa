<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\HelpController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\PageAdminController;
use App\Http\Controllers\Admin\PageBlockController;
use App\Http\Controllers\Admin\ResourceController;
use App\Http\Controllers\Admin\SubmissionReviewController;
use App\Http\Controllers\Admin\TranslationController;
use App\Http\Controllers\Admin\UserAdminController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Auth\TwoFactorEnrolmentController;
use App\Http\Controllers\ComparatorController;
use App\Http\Controllers\CompletenessController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\LocaleRedirectController;
use App\Http\Controllers\NewsroomController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PublicationController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\SubmissionImportController;
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
         * The regulatory comparator, RP-10, screen 03.
         *
         * Every state is a query string, because a regulator pastes these
         * into an email and the recipient has to see the same figures. The
         * exports read the same Comparison object as the page.
         */
        Route::get('comparator', [ComparatorController::class, 'index'])->name('comparator');
        Route::get('comparator/data', [ComparatorController::class, 'data'])->name('comparator.data');
        Route::get('comparator/export.csv', [ComparatorController::class, 'csv'])->name('comparator.csv');
        Route::get('comparator/export.png', [ComparatorController::class, 'image'])->name('comparator.png');
        Route::get('comparator/export.pdf', [ComparatorController::class, 'pdf'])->name('comparator.pdf');

        /*
         * Drawn in the screen 02 footer; the feature itself is a later
         * phase. See PublicPagesSeeder::awaitingFeature().
         */
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

            /*
             * The e-Form, RP-09, screen 06. A member state representative
             * fills in their own country's return. Everything they reach is
             * their own: the country scope holds the reads and the policy
             * holds the writes.
             */
            Route::get('submissions', [SubmissionController::class, 'index'])->name('submissions');
            Route::post('submissions', [SubmissionController::class, 'store'])->name('submissions.store');
            /*
             * Constrained to digits, or "submissions/template.csv" would be
             * matched as a submission called "template.csv" and 404 on a
             * route that exists.
             */
            Route::get('submissions/{submission}', [SubmissionController::class, 'edit'])
                ->whereNumber('submission')->name('submissions.edit');
            Route::put('submissions/{submission}', [SubmissionController::class, 'update'])->name('submissions.update');
            Route::post('submissions/{submission}/submit', [SubmissionController::class, 'submit'])->name('submissions.submit');

            /*
             * Bulk import and export, RP-08 and BUILD_SPEC 6.2. Two steps:
             * inspect reports, commit writes, and commit writes everything
             * or nothing.
             */
            Route::get('submissions/template.csv', [SubmissionImportController::class, 'template'])->name('submissions.template');
            Route::get('submissions/{submission}/import', [SubmissionImportController::class, 'show'])->name('submissions.import');
            Route::post('submissions/{submission}/import', [SubmissionImportController::class, 'inspect'])->name('submissions.import.inspect');
            Route::post('submissions/{submission}/import/commit', [SubmissionImportController::class, 'commit'])->name('submissions.import.commit');
            Route::get('submissions/{submission}/export.csv', [SubmissionImportController::class, 'exportCsv'])->name('submissions.export.csv');
            Route::get('submissions/{submission}/export.json', [SubmissionImportController::class, 'exportJson'])->name('submissions.export.json');

            Route::get('account', [AccountController::class, 'show'])->name('account');
            Route::put('account', [AccountController::class, 'update'])->name('account.update');
            Route::put('account/password', [AccountController::class, 'updatePassword'])->name('account.password');
            Route::put('account/theme', [AccountController::class, 'updateTheme'])->name('account.theme');

            Route::get('two-factor/enrol', [TwoFactorEnrolmentController::class, 'show'])->name('two-factor.enrol');
            Route::post('two-factor/confirm', [TwoFactorEnrolmentController::class, 'confirm'])->name('two-factor.confirm');
            Route::post('two-factor/recovery-codes', [TwoFactorEnrolmentController::class, 'regenerate'])->name('two-factor.regenerate');
            Route::delete('two-factor', [TwoFactorEnrolmentController::class, 'destroy'])->name('two-factor.destroy');

            /*
             * Administration. Every action re-checks the policy; the
             * navigation is not the access control.
             */
            Route::prefix('admin')->name('admin.')->group(function () {
                Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
                /*
                 * Content. CM-01: the page list, the page, its blocks and
                 * the workflow. Every action re-checks its policy; the
                 * navigation is not the access control.
                 */
                Route::get('pages', [PageAdminController::class, 'index'])->name('pages');
                Route::get('pages/{page}', [PageAdminController::class, 'edit'])->name('pages.edit');
                Route::put('pages/{page}', [PageAdminController::class, 'update'])->name('pages.update');
                Route::put('pages/{page}/status', [PageAdminController::class, 'updateStatus'])->name('pages.status');
                Route::get('pages/{page}/preview', [PageAdminController::class, 'preview'])->name('pages.preview');

                Route::get('pages/{page}/blocks/add', [PageBlockController::class, 'create'])->name('pages.blocks.create');
                Route::post('pages/{page}/blocks', [PageBlockController::class, 'store'])->name('pages.blocks.store');
                Route::get('pages/{page}/blocks/{block}', [PageBlockController::class, 'edit'])->name('pages.blocks.edit');
                Route::put('pages/{page}/blocks/{block}', [PageBlockController::class, 'update'])->name('pages.blocks.update');
                Route::put('pages/{page}/blocks/{block}/move', [PageBlockController::class, 'move'])->name('pages.blocks.move');
                Route::post('pages/{page}/blocks/{block}/duplicate', [PageBlockController::class, 'duplicate'])->name('pages.blocks.duplicate');
                Route::put('pages/{page}/blocks/{block}/visibility', [PageBlockController::class, 'toggle'])->name('pages.blocks.visibility');
                Route::delete('pages/{page}/blocks/{block}', [PageBlockController::class, 'destroy'])->name('pages.blocks.destroy');

                /*
                 * Media, CM-03. Nothing is hotlinked: every asset is stored
                 * here and served from a generated rendition.
                 */
                Route::get('media', [MediaController::class, 'index'])->name('media');
                Route::get('media/upload', [MediaController::class, 'create'])->name('media.create');
                Route::post('media', [MediaController::class, 'store'])->name('media.store');
                Route::get('media/{asset}', [MediaController::class, 'edit'])->name('media.edit');
                Route::put('media/{asset}', [MediaController::class, 'update'])->name('media.update');
                Route::post('media/{asset}/replace', [MediaController::class, 'replace'])->name('media.replace');
                Route::delete('media/{asset}', [MediaController::class, 'destroy'])->name('media.destroy');

                /*
                 * Translations, CM-04. Screen 05. Nothing here creates a
                 * machine draft; it only lets an editor see, approve or
                 * rewrite one that arrived with imported content.
                 */
                Route::get('translations', [TranslationController::class, 'index'])->name('translations');
                Route::get('translations/{type}/{id}', [TranslationController::class, 'edit'])->name('translations.edit');
                Route::put('translations/{type}/{id}', [TranslationController::class, 'update'])->name('translations.update');
                Route::put('translations/{type}/{id}/approve', [TranslationController::class, 'approve'])->name('translations.approve');
                Route::get('translations/{type}/{id}/preview/{previewLocale}', [TranslationController::class, 'preview'])->name('translations.preview');

                /*
                 * Every other administered model. Screen 09: "The repeating
                 * resource table. Every content type in the back office uses
                 * this pattern."
                 */
                /*
                 * Onboarding. Not an overlay walkthrough: a panel on the
                 * page it describes, permanently re-readable here.
                 */
                Route::get('help', [HelpController::class, 'index'])->name('help');
                Route::post('help/dismiss', [HelpController::class, 'dismiss'])->name('help.dismiss');
                Route::post('help/restore', [HelpController::class, 'reset'])->name('help.reset');

                Route::get('data', [ResourceController::class, 'hub'])->name('data');
                Route::get('data/{resource}', [ResourceController::class, 'index'])->name('resources');
                Route::get('data/{resource}/new', [ResourceController::class, 'create'])->name('resources.create');
                Route::post('data/{resource}', [ResourceController::class, 'store'])->name('resources.store');
                Route::get('data/{resource}/{id}', [ResourceController::class, 'edit'])->name('resources.edit');
                Route::put('data/{resource}/{id}', [ResourceController::class, 'update'])->name('resources.update');
                Route::delete('data/{resource}/{id}', [ResourceController::class, 'destroy'])->name('resources.destroy');
                Route::put('data/{resource}/{id}/restore', [ResourceController::class, 'restore'])->name('resources.restore');

                /*
                 * The Secretariat's review queue, RP-09. Submissions
                 * waiting on a decision come first.
                 */
                Route::get('submissions', [SubmissionReviewController::class, 'index'])->name('submissions');
                Route::get('submissions/{submission}', [SubmissionReviewController::class, 'show'])->name('submissions.show');
                Route::post('submissions/{submission}/comment', [SubmissionReviewController::class, 'comment'])->name('submissions.comment');
                Route::post('submissions/{submission}/decide', [SubmissionReviewController::class, 'decide'])->name('submissions.decide');

                Route::get('users', [UserAdminController::class, 'index'])->name('users');
                Route::get('users/invite', [UserAdminController::class, 'form'])->name('users.form');
                Route::post('users/invite', [UserAdminController::class, 'invite'])->name('users.invite');
                Route::put('users/{user}/role', [UserAdminController::class, 'assignRole'])->name('users.role');
                Route::put('users/{user}/suspend', [UserAdminController::class, 'suspend'])->name('users.suspend');
                Route::put('users/{user}/reactivate', [UserAdminController::class, 'reactivate'])->name('users.reactivate');
                Route::get('audit', [AuditLogController::class, 'index'])->name('audit');
            });
        });

        /*
         * Any other published page, by slug.
         *
         * This is last on purpose: every route above is matched first, so
         * naming a page "publications" cannot capture the library. It is
         * what makes the back office worth having -- a page created by the
         * Secretariat is served without a deployment -- and it is scoped to
         * a single segment so it cannot swallow a nested route.
         */
        Route::get('{slug}', [PageController::class, 'showBySlug'])
            ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
            ->name('page');

    });

<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AdminSearchController;
use App\Http\Controllers\Admin\AnnouncementAdminController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\AuthorityChangeController;
use App\Http\Controllers\Admin\CommitteeSeatController;
use App\Http\Controllers\Admin\ConfirmationController;
use App\Http\Controllers\Admin\ContributionReviewController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DelegateListController;
use App\Http\Controllers\Admin\DismissController;
use App\Http\Controllers\Admin\HelpController;
use App\Http\Controllers\Admin\InstrumentVersionController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MeetingListController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\PageAdminController;
use App\Http\Controllers\Admin\PageBlockController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ResourceController;
use App\Http\Controllers\Admin\SampleDataController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SubmissionReviewController;
use App\Http\Controllers\Admin\TranslationController;
use App\Http\Controllers\Admin\UserAdminController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AppManifestController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\SsoController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Auth\TwoFactorEnrolmentController;
use App\Http\Controllers\AuthorityProfileController;
use App\Http\Controllers\ComparatorController;
use App\Http\Controllers\CompletenessController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContributionController;
use App\Http\Controllers\DelegateRegistrationController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EventsController;
use App\Http\Controllers\ExtranetController;
use App\Http\Controllers\FeatureInterestController;
use App\Http\Controllers\IndicatorReferenceController;
use App\Http\Controllers\LegacyRedirectController;
use App\Http\Controllers\LocaleRedirectController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\NewsroomController;
use App\Http\Controllers\NotificationCentreController;
use App\Http\Controllers\HomeComparisonController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PublicationController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\SubmissionImportController;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\VerifyTurnstile;
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
        // The homepage comparison's table alone, for the block's script.
        Route::get('home/comparison', HomeComparisonController::class)->name('home.comparison');

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
        /*
         * The web app manifest, WS-02. In the locale group because the
         * start URL carries the reader's language: a fixed one installs an
         * English app on a Portuguese reader's phone.
         */
        Route::get('manifest.webmanifest', AppManifestController::class)->name('manifest');

        /*
         * The offline page, screen 15. Precached in all three languages, so
         * a reader who loses their connection in Portuguese does not meet
         * an English error.
         */
        Route::view('offline', 'pages.app.offline')->name('offline');

        Route::get('about', [PageController::class, 'show'])->defaults('slug', 'about')->name('about');
        Route::get('committees', [PageController::class, 'show'])->defaults('slug', 'committees')->name('committees');
        Route::get('committees/{committee}', [\App\Http\Controllers\CommitteePageController::class, 'show'])->name('committees.show');
        Route::get('members', [PageController::class, 'show'])->defaults('slug', 'members')->name('members');

        /*
         * The dropdown alternative to the map.
         *
         * Screen 08 says "Select a member state" beside the map, and a map
         * is not a control everyone can use: a reader who knows they want
         * Zambia should not have to find Zambia on a map, and a reader who
         * cannot see the map has no route through it at all.
         *
         * A GET form needs a real action, and the member page's URL has the
         * code in its path rather than its query string, so this redirects.
         * One route, no script, and the reader lands on the same canonical
         * URL the map's links use.
         */
        Route::get('member', [MemberController::class, 'redirectToState'])->name('members.go');

        /*
         * The shaded map as a briefing document, matching the comparator's
         * exports. A choropleth that has to be screenshotted to reach a
         * plenary paper is half a feature.
         */
        Route::get('members/export.{format}', [MemberController::class, 'export'])
            ->whereIn('format', ['png', 'pdf'])
            ->name('members.export');

        /*
         * One member state. The map on the membership page links here, and a
         * link that only scrolls the page it is on is not navigation.
         *
         * Keyed by ISO code rather than by an id, so the URL a regulator
         * pastes into a minute reads /members/bw and survives any reseed.
         */
        Route::get('members/{iso}', [MemberController::class, 'show'])
            ->whereAlpha('iso')
            ->name('members.show');

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
        Route::get('comparator/export.xlsx', [ComparatorController::class, 'xlsx'])->name('comparator.xlsx');
        Route::get('comparator/export.png', [ComparatorController::class, 'image'])->name('comparator.png');
        Route::get('comparator/export.pdf', [ComparatorController::class, 'pdf'])->name('comparator.pdf');

        /*
         * The public events section, WS-07. Read only: the website
         * displays and links out to registration, the Event Management
         * Platform manages. There is no route here that creates or edits
         * an event, and that is the boundary rather than an omission.
         */
        Route::get('events', [EventsController::class, 'index'])->name('events');
        Route::get('events/{meeting}', [EventsController::class, 'show'])->name('events.show');
        Route::get('events/{meeting}/calendar.ics', [EventsController::class, 'ics'])->name('events.ics');
        Route::get('events/{meeting}/register', [DelegateRegistrationController::class, 'create'])->name('events.register');
        // Throttled: a public form that writes a row, on a page anyone can
        // reach. Loose enough that a delegate who mistypes a date three
        // times is not locked out.
        Route::post('events/{meeting}/register', [DelegateRegistrationController::class, 'store'])
            ->middleware(['throttle:registration', VerifyTurnstile::class])
            ->name('events.register.store');

        Route::get('privacy', [PageController::class, 'show'])->defaults('slug', 'privacy')->name('privacy');
        Route::get('terms', [PageController::class, 'show'])->defaults('slug', 'terms')->name('terms');
        Route::get('accessibility', [PageController::class, 'show'])->defaults('slug', 'accessibility')->name('accessibility');

        /*
         * The extranet is phase 3. Its entry point is the sign-in page,
         * which is where the header action and the footer link both go
         * rather than to a 404.
         */
        /*
         * The extranet entry point. A signed-in account goes to its
         * folders; a visitor goes to sign in, which is where the extranet
         * begins for them.
         */
        /*
         * Demand capture for a proposal that is not built and is outside
         * the contracted scope. A row and an audit entry; nothing else.
         * See docs/OPEN_ITEMS.md, and FeatureInterestController.
         */
        Route::post('extranet/interest', [FeatureInterestController::class, 'store'])
            ->middleware(['auth', 'throttle:feature-interest'])
            ->name('extranet.interest');

        // A signed-in account with no member area (a viewer) is refused here
        // with the friendly 403 page, not sent on to a page that refuses it
        // (PM-01).
        Route::get('extranet', function () {
            if (! auth()->check()) {
                return redirect()->route('login', ['locale' => app()->getLocale()]);
            }
            abort_unless(auth()->user()->can('enter-member-area'), 403);

            return redirect()->route('extranet.dashboard', ['locale' => app()->getLocale()]);
        })->name('extranet');

        /*
         * Search. WS-04. Behind a driver interface, so a managed
         * ElasticSearch service later is a config change.
         */
        Route::get('search', [SearchController::class, 'index'])->name('search');
        Route::get('search/suggest', [SearchController::class, 'suggest'])
            ->middleware('throttle:search-suggest')->name('search.suggest');

        /*
         * The regulatory library. WS-05.
         */
        Route::get('publications', [PublicationController::class, 'index'])->name('publications');
        Route::get('publications/{slug}', [PublicationController::class, 'show'])->name('publications.show');

        /*
         * Projects. WS-12's no-content-loss clause.
         *
         * The legacy site carries two project sections behind a top-level
         * menu item and WS-03's information architecture lists neither.
         * Current and past are one collection in two states, so the detail
         * URL does not change on the day a project ends.
         */
        Route::get('projects', [ProjectController::class, 'index'])->name('projects');
        Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
        /*
         * Newsroom. WS-06.
         */
        Route::get('newsroom', [NewsroomController::class, 'index'])->name('newsroom');
        Route::get('newsroom/{slug}', [NewsroomController::class, 'show'])->name('newsroom.show');

        /*
         * Contact. The rate limit is per IP and deliberately loose: six an
         * hour stops a script without stopping a regulator who sends two
         * enquiries in a morning. No puzzle CAPTCHA, which WCAG 2.1 AA and
         * CLAUDE.md both point away from: Cloudflare Turnstile when its keys
         * are set (VerifyTurnstile, App\Support\Turnstile), nothing extra
         * when they are not.
         */
        Route::get('contact', [ContactController::class, 'show'])->name('contact');
        Route::post('contact', [ContactController::class, 'store'])
            ->middleware(['throttle:enquiries', VerifyTurnstile::class])
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
         * is not offered a login form. Each path has its own named limiter;
         * sign-in failures are counted per account in LoginController.
         */
        Route::middleware('guest')->group(function () {
            Route::get('login', [LoginController::class, 'show'])->name('login');
            /*
             * The controller counts failures per account, wherever they come
             * from, and failures per IP across accounts, so spraying one
             * password across many accounts is bounded. This only caps raw
             * volume from one address, successes included, and is set well
             * above a workshop room on one Wi-Fi. Every path below has its
             * own named limiter (AppServiceProvider), so none shares a
             * counter with sign-in.
             */
            Route::post('login', [LoginController::class, 'store'])
                ->middleware(['throttle:sign-in', VerifyTurnstile::class])
                ->name('login.store');

            Route::get('password/forgotten', [PasswordResetController::class, 'request'])->name('password.request');
            Route::post('password/forgotten', [PasswordResetController::class, 'email'])
                ->middleware(['throttle:password-email', VerifyTurnstile::class])->name('password.email');
            Route::get('password/reset/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
            Route::post('password/reset', [PasswordResetController::class, 'update'])
                ->middleware(['throttle:password-update', VerifyTurnstile::class])->name('password.update');

            /*
             * The SSO handoff and its error states, IN-01.
             *
             * Present and tested even though no provider is configured,
             * because IN-01's deliverable is the handoff and its failure
             * paths, and the failure paths are the part that is hard to
             * add later.
             */
            Route::get('sso/redirect', [SsoController::class, 'redirect'])->name('sso.redirect');
            Route::get('sso/callback', [SsoController::class, 'callback'])->name('sso.callback');
            // Throttled like the other authentication paths: an assertion
            // endpoint a stranger can post to is an authentication path
            // whether or not a provider is configured today.
            Route::post('sso/callback', [SsoController::class, 'callback'])
                ->middleware('throttle:sso-callback')
                ->name('sso.callback.post');

            Route::get('two-factor/challenge', [TwoFactorChallengeController::class, 'show'])
                ->name('two-factor.challenge');
            /*
             * The controller already locks an account out after five wrong
             * codes. This is the layer above it: a single address trying
             * codes against many accounts never reaches that per-account
             * counter.
             */
            Route::post('two-factor/challenge', [TwoFactorChallengeController::class, 'store'])
                ->middleware('throttle:two-factor')
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
            /*
             * The extranet, IN-02 and WS-09. Screens 04, 11 and 12.
             *
             * Zero trust: a folder belonging to another member state is
             * not listed, not searchable and not reachable by guessing an
             * id, because the global scope makes the query return nothing.
             */
            /*
             * Screen 11's landing page. A member used to sign in and be
             * redirected past this into the folder list, so the extranet's
             * first sentence to a national regulator was a file browser.
             */
            Route::get('extranet/dashboard', [ExtranetController::class, 'dashboard'])->middleware('can:enter-member-area')->name('extranet.dashboard');
            Route::get('extranet/folders', [ExtranetController::class, 'index'])->middleware('can:enter-member-area')->name('extranet.folders');
            Route::get('extranet/folders/{folder}', [ExtranetController::class, 'folder'])->middleware('can:enter-member-area')->name('extranet.folder');
            Route::post('extranet/folders/{folder}/upload', [ExtranetController::class, 'upload'])->middleware('can:enter-member-area')->name('extranet.upload');
            Route::get('extranet/committees/{committee}', [ExtranetController::class, 'committee'])->middleware('can:enter-member-area')->name('extranet.committee');
            Route::get('extranet/search', [ExtranetController::class, 'search'])->middleware('can:enter-member-area')->name('extranet.search');
            Route::get('extranet/calendar', [ExtranetController::class, 'calendar'])->middleware('can:enter-member-area')->name('extranet.calendar');
            // Screen 12 draws an export beside the month grid. A meeting a
            // regulator cannot put in their own calendar is one they miss.
            Route::get('extranet/calendar.ics', [ExtranetController::class, 'calendarExport'])->middleware('can:enter-member-area')->name('extranet.calendar.ics');

            /*
             * Contributing a national instrument, RP-03 and RP-06.
             *
             * This is how the register fills: from fourteen members, not
             * from a Secretariat of five typing in fourteen countries'
             * legislation. Country scope is enforced on the write, not on
             * the read: the library is deliberately readable across all
             * fourteen so the comparator works.
             */
            Route::get('contributions', [ContributionController::class, 'index'])->name('contributions');
            Route::get('contributions/new', [ContributionController::class, 'create'])->name('contributions.create');
            Route::post('contributions', [ContributionController::class, 'store'])->name('contributions.store');
            Route::get('contributions/{instrument}', [ContributionController::class, 'edit'])->name('contributions.edit');
            Route::put('contributions/{instrument}', [ContributionController::class, 'update'])->name('contributions.update');
            Route::post('contributions/{instrument}/submit', [ContributionController::class, 'submit'])->name('contributions.submit');

            /*
             * The harmonised register, read by any signed-in member.
             *
             * Not country-scoped, deliberately: it exists so fourteen
             * regulators can read each other's instruments, which is the
             * harmonisation mandate. Contributing and editing are scoped;
             * reading is not.
             */
            Route::get('register', [RegisterController::class, 'index'])->middleware('can:enter-member-area')->name('register');
            Route::get('register/{instrument}', [RegisterController::class, 'show'])->middleware('can:enter-member-area')->name('register.show');

            /*
             * The indicator taxonomy, RP-11 and extranet item 11.
             *
             * The definitions were stored and appeared only as a truncated
             * hint under a form field. If fourteen regulators are to report
             * the same thing, they need somewhere to read what the thing
             * is.
             */
            Route::get('indicators', [IndicatorReferenceController::class, 'index'])->middleware('can:enter-member-area')->name('indicators.reference');

            /*
             * The member state's own record, extranet item 3.
             *
             * A proposal rather than an edit: the authority record is what
             * the public members page shows and what a ministry cites, so
             * it does not change without the Secretariat seeing it.
             */
            Route::get('authority', [AuthorityProfileController::class, 'show'])->name('authority');
            Route::put('authority', [AuthorityProfileController::class, 'update'])->name('authority.update');

            /*
             * The members directory, extranet item 4.
             *
             * Members only. There is no public route to this table, no CSV
             * of it and no report that reads it: a directory of fourteen
             * regulators' direct lines is exactly the file that must not
             * leave the extranet. Consent gates every listing.
             */
            Route::get('directory', [DirectoryController::class, 'index'])->middleware('can:enter-member-area')->name('directory');
            Route::post('directory', [DirectoryController::class, 'store'])->middleware('can:enter-member-area')->name('directory.store');
            Route::put('directory/{contact}', [DirectoryController::class, 'update'])->middleware('can:enter-member-area')->name('directory.update');
            Route::delete('directory/{contact}', [DirectoryController::class, 'destroy'])->middleware('can:enter-member-area')->name('directory.destroy');

            /*
             * Secretariat announcements, RP-05 and extranet item 10.
             *
             * Members only, and the model's scope is what makes that true:
             * an announcement narrowed to one committee is not listed, not
             * linked and not reachable by guessing a slug for anyone
             * without that seat.
             */
            Route::get('announcements', [AnnouncementController::class, 'index'])->middleware('can:enter-member-area')->name('announcements');
            Route::get('announcements/{announcement}', [AnnouncementController::class, 'show'])->middleware('can:enter-member-area')->name('announcements.show');

            /*
             * The notification centre, RP-05 and extranet item 6.
             *
             * The bell pointed at an anchor on the account page, where the
             * five most recent notices sat under the password form.
             */
            Route::get('notifications', [NotificationCentreController::class, 'index'])->name('notifications');
            Route::post('notifications/read-all', [NotificationCentreController::class, 'readAll'])->name('notifications.read-all');
            Route::put('notifications/preferences', [NotificationCentreController::class, 'preferences'])->name('notifications.preferences');
            Route::get('notifications/{notification}', [NotificationCentreController::class, 'open'])->name('notifications.open');

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
                Route::put('pages/{page}/client-review', [PageAdminController::class, 'clientReview'])->name('pages.client-review');
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
                 * Confirming the member state records, built for the
                 * validation workshop: the fourteen delegates who can
                 * confirm will be in one room, and a request that leaves in
                 * a folder comes back in months if at all.
                 */
                Route::get('confirmations', [ConfirmationController::class, 'index'])->name('confirmations');
                Route::get('confirmations/{iso}', [ConfirmationController::class, 'edit'])
                    ->whereAlpha('iso')->name('confirmations.edit');
                Route::put('confirmations/{iso}', [ConfirmationController::class, 'update'])
                    ->whereAlpha('iso')->name('confirmations.update');

                /*
                 * The navigation builder, screen 14.
                 *
                 * The header read a config array and the footer's columns
                 * were written into a Blade template, so renaming a menu
                 * item meant a deploy.
                 */
                Route::get('menus', [MenuController::class, 'index'])->name('menus');
                Route::post('menus', [MenuController::class, 'store'])->name('menus.store');
                Route::put('menus/{item}', [MenuController::class, 'update'])->name('menus.update');
                Route::put('menus/{item}/move', [MenuController::class, 'move'])->name('menus.move');
                Route::put('menus/{item}/visibility', [MenuController::class, 'toggle'])->name('menus.visibility');
                Route::delete('menus/{item}', [MenuController::class, 'destroy'])->name('menus.destroy');

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
                 * Site settings, CM-05. The Secretariat's own contact
                 * details, CRASA's accounts, the legacy site's address:
                 * the things that belong to the site rather than to a
                 * page. Administrator only, per screen 13's matrix.
                 */
                /*
                 * The search field in the top bar. Six record types, each
                 * gated separately: a search that lists what you cannot
                 * open is a directory of what you are not allowed to see.
                 */
                Route::get('search', [AdminSearchController::class, 'index'])->name('search');

                /*
                 * What demonstration data is left, per table. No purge
                 * button: the strip deletes two hundred rows in one
                 * transaction and belongs at a terminal, not behind a
                 * mis-click.
                 */
                Route::get('samples', [SampleDataController::class, 'index'])->name('samples');

                // Putting an informational alert away for the session. The
                // mail warning is not on the list and cannot be.
                Route::post('dismiss', [DismissController::class, 'store'])->name('dismiss');

                Route::get('settings', [SettingsController::class, 'edit'])->name('settings');
                Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');

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

                /*
                 * Regional briefs, RP-12. Generate, view, store, print.
                 */
                /*
                 * The delegate list, WS-07. A plain sortable list and a
                 * CSV export, which is the handover point to the Event
                 * Management Platform.
                 */
                /*
                 * The way in to the rosters. Each one was reachable only by
                 * knowing a meeting's slug.
                 */
                /*
                 * Committee seats. Which member states sit on which
                 * committee, and in what role. This is what the
                 * confirmation session in Lilongwe writes into.
                 */
                /*
                 * An instrument's history and its diff. RP-07 has written
                 * a version on every edit since the schema was created and
                 * nothing read it, so the history was a count rather than
                 * a record.
                 */
                Route::get('instruments/{instrument}/versions', [InstrumentVersionController::class, 'index'])->name('instruments.versions');
                Route::get('instruments/{instrument}/diff', [InstrumentVersionController::class, 'diff'])->name('instruments.diff');

                Route::get('committees/{committee}/seats', [CommitteeSeatController::class, 'index'])->name('committees.seats');
                Route::post('committees/{committee}/seats', [CommitteeSeatController::class, 'store'])->name('committees.seats.store');
                Route::put('committees/{committee}/seats/{country}', [CommitteeSeatController::class, 'update'])->name('committees.seats.update');
                Route::delete('committees/{committee}/seats/{country}', [CommitteeSeatController::class, 'destroy'])->name('committees.seats.destroy');

                Route::get('meetings', [MeetingListController::class, 'index'])->name('meetings');
                Route::get('meetings/{meeting}/delegates', [DelegateListController::class, 'index'])->name('delegates');
                Route::get('meetings/{meeting}/delegates.csv', [DelegateListController::class, 'csv'])->name('delegates.csv');

                Route::get('reports', [ReportController::class, 'index'])->name('reports');
                Route::post('reports', [ReportController::class, 'store'])->name('reports.store');
                Route::get('reports/{report}', [ReportController::class, 'show'])->name('reports.show');
                Route::get('reports/{report}/export.pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
                Route::delete('reports/{report}', [ReportController::class, 'destroy'])->name('reports.destroy');

                Route::get('users', [UserAdminController::class, 'index'])->name('users');
                Route::get('users/invite', [UserAdminController::class, 'form'])->name('users.form');
                Route::post('users/invite', [UserAdminController::class, 'invite'])->name('users.invite');
                Route::put('users/{user}/role', [UserAdminController::class, 'assignRole'])->name('users.role');
                Route::put('users/{user}/suspend', [UserAdminController::class, 'suspend'])->name('users.suspend');
                Route::put('users/{user}/reactivate', [UserAdminController::class, 'reactivate'])->name('users.reactivate');
                /*
                 * Reviewing what the member states contributed, RP-06. The
                 * same queue as the data returns, because it is the same
                 * job done by the same three people.
                 */
                Route::get('contributions', [ContributionReviewController::class, 'index'])->name('contributions');
                Route::get('contributions/{instrument}', [ContributionReviewController::class, 'show'])->name('contributions.show');
                Route::put('contributions/{instrument}/validate', [ContributionReviewController::class, 'validateContribution'])->name('contributions.validate');
                Route::put('contributions/{instrument}/return', [ContributionReviewController::class, 'returnContribution'])->name('contributions.return');

                /*
                 * Approving a member state's proposed record. The same act
                 * as the Lilongwe confirmation, running the same apply
                 * step.
                 */
                Route::get('authority-changes', [AuthorityChangeController::class, 'index'])->name('authority-changes');
                Route::get('authority-changes/{change}', [AuthorityChangeController::class, 'show'])->whereNumber('change')->name('authority-changes.show');
                Route::put('authority-changes/{change}/approve', [AuthorityChangeController::class, 'approve'])->whereNumber('change')->name('authority-changes.approve');
                Route::put('authority-changes/{change}/return', [AuthorityChangeController::class, 'returnChange'])->whereNumber('change')->name('authority-changes.return');

                /*
                 * Writing an announcement. Publishing is a separate route
                 * from saving, because publishing notifies fourteen
                 * regulators and that is not the same decision.
                 */
                Route::get('announcements', [AnnouncementAdminController::class, 'index'])->name('announcements');
                Route::get('announcements/new', [AnnouncementAdminController::class, 'create'])->name('announcements.create');
                Route::post('announcements', [AnnouncementAdminController::class, 'store'])->name('announcements.store');
                Route::get('announcements/{announcement}', [AnnouncementAdminController::class, 'edit'])->name('announcements.edit');
                Route::put('announcements/{announcement}', [AnnouncementAdminController::class, 'update'])->name('announcements.update');
                Route::put('announcements/{announcement}/publish', [AnnouncementAdminController::class, 'publish'])->name('announcements.publish');
                Route::put('announcements/{announcement}/unpublish', [AnnouncementAdminController::class, 'unpublish'])->name('announcements.unpublish');

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

/*
 * A legacy URL that matched nothing above.
 *
 * Last in the file and outside the locale group, because a legacy URL
 * carries no language prefix and because only a request that matched no
 * route should pay for the lookup. Anything with no mapping falls through
 * to the ordinary 404.
 */
Route::fallback(LegacyRedirectController::class);

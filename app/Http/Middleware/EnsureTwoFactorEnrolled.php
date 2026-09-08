<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 2FA is mandatory for administrators, enforced at login rather than offered
 * in settings. An administrator who has not confirmed enrolment is sent to
 * enrolment and reaches nothing else.
 *
 * This is the first of two layers. The second is in User::hasPermission(),
 * which withholds the privileged abilities from an unenrolled administrator
 * regardless of how the request arrived, so a route added later without this
 * middleware still cannot publish, validate a submission or change a role.
 */
class EnsureTwoFactorEnrolled
{
    /** Routes reachable while enrolment is outstanding, or the redirect loops. */
    private const EXEMPT = [
        'two-factor.enrol',
        'two-factor.confirm',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->mustEnrolInTwoFactor()) {
            return $next($request);
        }

        if (in_array($request->route()?->getName(), self::EXEMPT, true)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Two-factor enrolment is required before this account can be used.');
        }

        // The locale is resolved explicitly rather than left to URL defaults.
        // This middleware runs on the whole web group, including routes
        // outside the locale prefix where no default has been registered,
        // and preferred_locale is the account's own answer to the question.
        return redirect()->route('two-factor.enrol', [
            'locale' => $this->localeFor($user),
        ]);
    }

    private function localeFor(User $user): string
    {
        $locale = $user->preferred_locale ?: app()->getLocale();

        return Locales::isSupported($locale)
            ? $locale
            : Locales::FALLBACK;
    }
}

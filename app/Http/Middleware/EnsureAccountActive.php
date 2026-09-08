<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ruling 3. A suspended account authenticates and then reaches nothing but
 * the page telling the holder who to contact.
 *
 * Failing at the password prompt instead would be indistinguishable from a
 * forgotten password, and would generate a support call rather than resolve
 * one.
 */
class EnsureAccountActive
{
    private const EXEMPT = ['account.suspended', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->mayUsePlatform()) {
            return $next($request);
        }

        if (in_array($request->route()?->getName(), self::EXEMPT, true)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'This account is suspended.');
        }

        $locale = Locales::isSupported($user->preferred_locale)
            ? $user->preferred_locale
            : Locales::FALLBACK;

        return redirect()->route('account.suspended', ['locale' => $locale]);
    }
}

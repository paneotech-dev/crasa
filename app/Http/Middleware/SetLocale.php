<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the language named in the first path segment.
 *
 * The locale is in the URL rather than only in the session so that every page
 * has a distinct address per language, which is what search engines, shared
 * links and ministerial citations need.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale');

        abort_unless(Locales::isSupported($locale), 404);

        app()->setLocale($locale);

        if ($request->hasSession()) {
            $request->session()->put('locale', $locale);
        }

        // Route helpers keep the current language without every call passing it.
        URL::defaults(['locale' => $locale]);

        // Controllers and route model binding do not need the segment.
        $request->route()->forgetParameter('locale');

        return $next($request);
    }
}

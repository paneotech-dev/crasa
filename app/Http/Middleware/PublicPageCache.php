<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Cache-Control for public pages, in agreement with the Cloudflare rule.
 *
 * A page may be kept by Cloudflare and the browser for ten minutes only
 * when it is the same page for everyone:
 *
 *   - a GET or HEAD under /en, /fr or /pt, answered 200 with HTML
 *   - the visitor sent no session, remember-me or XSRF cookie and no
 *     Authorization header, so nobody is signed in and nothing was flashed
 *   - the page carries no CSRF token (no POST form: contact, login,
 *     registration), because a cached token belongs to someone else's
 *     session and the form would fail for every later visitor
 *
 * Such a page is sent as "public, max-age=600" WITHOUT the session and
 * XSRF cookies Laravel sets on every response: a cached response that sets
 * a cookie would hand one visitor's session to the next. Laravel still
 * starts its session for the request; the cookie naming it is simply not
 * sent, so the visitor arrives at their next page without one, exactly as
 * they arrived at this one.
 *
 * Everything else is "private, no-store", except a response whose
 * controller already set its own policy (a public document download, or a
 * restricted one marked never stored).
 */
class PublicPageCache
{
    public const PUBLIC = 'public, max-age=600';

    public const PRIVATE = 'private, no-store';

    public function handle(Request $request, Closure $next): Response
    {
        $anonymous = $this->isAnonymous($request);

        $response = $next($request);

        if ($anonymous && $this->isCacheable($request, $response)) {
            foreach ($response->headers->getCookies() as $cookie) {
                $response->headers->removeCookie($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
            }

            $response->headers->set('Cache-Control', self::PUBLIC);

            return $response;
        }

        // A controller that already decided (a public document download,
        // a restricted one marked never stored) keeps its own header.
        if ($response->headers->hasCacheControlDirective('public')
            || $response->headers->hasCacheControlDirective('no-store')) {
            return $response;
        }

        $response->headers->set('Cache-Control', self::PRIVATE);

        return $response;
    }

    private function isAnonymous(Request $request): bool
    {
        if ($request->headers->has('Authorization')) {
            return false;
        }

        foreach (array_keys($request->cookies->all()) as $name) {
            if ($name === config('session.cookie')
                || $name === 'XSRF-TOKEN'
                || str_starts_with($name, 'remember_')) {
                return false;
            }
        }

        return true;
    }

    private function isCacheable(Request $request, Response $response): bool
    {
        if (! $request->isMethodCacheable()
            || ! in_array($request->segment(1), Locales::SUPPORTED, true)
            || $response->getStatusCode() !== 200
            || $response instanceof StreamedResponse
            || $response instanceof BinaryFileResponse
            || ! str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')) {
            return false;
        }

        // Signed in during this request, or something flashed for the next.
        if ($request->user() !== null) {
            return false;
        }

        if ($request->hasSession()) {
            $session = $request->session();

            if ($session->get('_flash.new', []) !== [] || $session->has('errors')) {
                return false;
            }
        }

        $content = (string) $response->getContent();

        return ! str_contains($content, 'name="_token"')
            && ! str_contains($content, 'name="csrf-token"');
    }
}

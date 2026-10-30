<?php

namespace App\Http\Middleware;

use App\Support\Turnstile;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The response headers a regulator's browser should be given.
 *
 * None of these was present. Each closes a class of attack that does not
 * need a bug in this application to work, which is why they belong here
 * rather than in a list of things to remember.
 *
 * The content security policy is the substantial one. This platform serves
 * every script, stylesheet, font and image from its own origin by design,
 * so the policy can say exactly that, and a script injected into a page
 * through any means at all will not run. On a platform whose stated
 * position is that nothing is hotlinked, the policy is a statement of what
 * is already true rather than a restriction on it.
 */
class SecurityHeaders
{
    /** The hosts the social post embeds load their scripts from. */
    public const EMBED_SCRIPTS = 'https://platform.twitter.com https://connect.facebook.net';

    /** The hosts that draw a social post in a frame. */
    public const EMBED_FRAMES = 'https://platform.twitter.com https://syndication.twitter.com https://www.facebook.com https://www.linkedin.com';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        /*
         * Everything from this origin. No inline scripts: the two scripts
         * this platform inlines are a one-line class toggle in the layout
         * head, which is why 'unsafe-inline' is granted to script-src-attr
         * alone rather than to script-src.
         *
         * frame-ancestors 'none' is the modern X-Frame-Options and stops
         * the back office being framed inside another page and clicked
         * through, which is the attack that matters for an administration
         * interface.
         */
        // Turnstile, when it is on, loads a script and draws its check in a
        // frame, both from Cloudflare's challenge host.
        $turnstile = Turnstile::enabled() ? ' '.Turnstile::HOST : '';

        $policy = implode('; ', array_filter([
            "default-src 'self'",
            // Cloudflare injects its Web Analytics beacon in front of the
            // site; without these two hosts the browser logs a CSP error on
            // every page.
            "script-src 'self' 'unsafe-inline' https://static.cloudflareinsights.com ".self::EMBED_SCRIPTS.$turnstile,
            "style-src 'self' 'unsafe-inline'",
            // The contact map's tile server, and only that host. Without it
            // the browser refused every tile and the map showed a pin on
            // an empty grey square.
            trim("img-src 'self' data: blob: ".$this->tileHost()),
            "font-src 'self'",
            "connect-src 'self' https://cloudflareinsights.com",
            "form-action 'self'",
            // CRASA's own social posts: X's post frame (drawn by its widget
            // script), Facebook's single-post plugin and LinkedIn's post
            // embed. Nothing else may be framed. One frame-src only: a
            // second directive of the same name is ignored by the browser.
            'frame-src '.($turnstile !== '' ? "'self'".$turnstile.' ' : '').self::EMBED_FRAMES,
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "object-src 'none'",
            'upgrade-insecure-requests',
        ]));

        $headers = [
            'Content-Security-Policy' => $policy,

            // A browser must not guess that an uploaded file is something
            // more interesting than its declared type.
            'X-Content-Type-Options' => 'nosniff',

            // For browsers predating frame-ancestors.
            'X-Frame-Options' => 'DENY',

            /*
             * An administration URL can name a member state and a document.
             * Sending that to whatever a reader clicks through to is a leak
             * even when the destination is harmless.
             */
            'Referrer-Policy' => 'strict-origin-when-cross-origin',

            // Nothing here needs a camera, a microphone or a location.
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',

            'Cross-Origin-Opener-Policy' => 'same-origin',
            // Our files are for our pages and our own subdomains (ZAP baseline, 30 October).
            'Cross-Origin-Resource-Policy' => 'same-site',
        ];

        // Only over TLS, and only for the real host: an HSTS header served
        // once over plain HTTP from a development machine pins that machine
        // to HTTPS in the browser for a year.
        if ($request->secure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        if (config('crasa.noindex')) {
            $headers['X-Robots-Tag'] = 'noindex, nofollow';
        }

        foreach ($headers as $name => $value) {
            // Never overwrite a header a controller set deliberately. The
            // media library sets its own policy on asset responses.
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    /** The scheme and host of the configured map tile server, or nothing. */
    private function tileHost(): string
    {
        $url = (string) config('crasa.map.tile_url');
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        if (! in_array($scheme, ['https', 'http'], true) || ! is_string($host) || ! preg_match('/^[a-z0-9.-]+$/i', $host)) {
            return '';
        }

        return $scheme.'://'.$host;
    }
}

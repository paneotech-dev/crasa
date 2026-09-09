<?php

namespace App\Http\Middleware;

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
        $policy = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "object-src 'none'",
            'upgrade-insecure-requests',
        ]);

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
        ];

        // Only over TLS, and only for the real host: an HSTS header served
        // once over plain HTTP from a development machine pins that machine
        // to HTTPS in the browser for a year.
        if ($request->secure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
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
}

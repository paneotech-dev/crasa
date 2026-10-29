<?php

namespace App\Http\Middleware;

use App\Support\Turnstile;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the Turnstile token on a public form before the controller sees
 * the request. A failure is a validation error on the widget's field, so
 * the reader is sent back to the form with what they typed and a message
 * saying what to do. Does nothing while Turnstile is off.
 *
 * Applied per route in routes/web.php, after the rate limiter, so a script
 * failing the check still spends its allowance.
 */
class VerifyTurnstile
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Turnstile::enabled() && ! Turnstile::verify($request->input(Turnstile::FIELD), $request->ip())) {
            throw ValidationException::withMessages([
                Turnstile::FIELD => __('captcha.failed'),
            ]);
        }

        return $next($request);
    }
}

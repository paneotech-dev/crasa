<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cloudflare Turnstile, the check behind the public forms.
 *
 * Off unless both keys are in the environment. Off, nothing is drawn and
 * nothing is verified, and the forms rely on their honeypot and their rate
 * limits as before.
 *
 * Turnstile rather than a puzzle CAPTCHA because it asks nothing of a
 * person in most cases and, when it does, the challenge is a single
 * checkbox that is keyboard operable and labelled for screen readers.
 */
final class Turnstile
{
    public const SCRIPT = 'https://challenges.cloudflare.com/turnstile/v0/api.js';

    public const VERIFY = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public const HOST = 'https://challenges.cloudflare.com';

    /** The field the widget adds to the form it sits in. */
    public const FIELD = 'cf-turnstile-response';

    public static function enabled(): bool
    {
        return filled(config('services.turnstile.site_key'))
            && filled(config('services.turnstile.secret'));
    }

    public static function siteKey(): ?string
    {
        return config('services.turnstile.site_key');
    }

    /**
     * True when Cloudflare confirms the token.
     *
     * An empty token fails without a request. When Cloudflare cannot be
     * reached at all the form is let through and the failure is logged: an
     * outage at Cloudflare must not lock every regulator out of sign-in,
     * and the rate limits and honeypot still stand in front of the form.
     * A definite "no" from Cloudflare always fails.
     */
    public static function verify(?string $token, ?string $ip = null): bool
    {
        if (! is_string($token) || trim($token) === '' || strlen($token) > 2048) {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post(self::VERIFY, array_filter([
                    'secret' => config('services.turnstile.secret'),
                    'response' => $token,
                    'remoteip' => $ip,
                ]));
        } catch (Throwable $e) {
            Log::warning('Turnstile could not be reached; the form was accepted on its other checks.', [
                'error' => $e->getMessage(),
            ]);

            return true;
        }

        if ($response->serverError()) {
            Log::warning('Turnstile returned a server error; the form was accepted on its other checks.', [
                'status' => $response->status(),
            ]);

            return true;
        }

        return $response->json('success') === true;
    }
}

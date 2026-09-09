<?php

namespace App\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * The documented fallback: this platform's own user store. IN-01.
 *
 * Not a stub and not a mock. It is a complete, deliverable answer to IN-01
 * that happens not to federate: accounts live here, passwords are hashed
 * here, two-factor is enforced here, and the audit trail records sign-ins
 * here. If Form 3 never arrives, this is what ships and it works.
 *
 * It reports itself as unconfigured for the redirect flow, because there is
 * nowhere to redirect to. The sign-in screen therefore offers the password
 * form alone rather than a button that leads nowhere.
 */
final class PlatformIdentityProvider implements IdentityProvider
{
    public function name(): string
    {
        return __('auth.provider_platform');
    }

    public function isConfigured(): bool
    {
        // There is no external provider, so there is no redirect flow to
        // offer. Everything else about this provider works.
        return false;
    }

    public function redirect(Request $request): RedirectResponse
    {
        return redirect()->route('login', ['locale' => app()->getLocale()]);
    }

    public function user(Request $request): ?User
    {
        // Authentication happens on the password form, which is the whole
        // point of the fallback. Nothing arrives here to assert.
        return null;
    }

    public function logoutUrl(): ?string
    {
        return null;
    }
}

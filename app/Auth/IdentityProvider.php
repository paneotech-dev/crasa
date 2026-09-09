<?php

namespace App\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * The seam an SSO bridge drops into. IN-01.
 *
 * Form 3 has never come back from CRASA, so there are no Extranet or EMP
 * platform details, no identity source and no test credentials. IN-01 needs
 * a deliverable path either way, so the platform authenticates against its
 * own user store today and does it behind this interface, which is the
 * shape a SAML 2.0 or OAuth 2.0 provider fits without the rest of the
 * application knowing.
 *
 * THREE THINGS AN IDENTITY PROVIDER HAS TO DO, and they are the whole
 * interface:
 *
 *   Send the browser somewhere to authenticate.
 *   Turn what comes back into a user of this platform, or refuse.
 *   Say where to send the browser on sign-out.
 *
 * Everything else about SAML and OAuth is inside an implementation. What
 * CRASA must supply for a real bridge is listed in docs/SSO_REQUIREMENTS.md,
 * which is written to be forwarded as it stands.
 */
interface IdentityProvider
{
    /** A short name for logs and for the sign-in screen. */
    public function name(): string;

    /**
     * Whether this provider is configured well enough to be used.
     *
     * False means the sign-in screen offers the password form alone. A
     * half-configured bridge must never be offered: a button that leads to
     * a provider error is worse than no button, because a regulator cannot
     * tell whether the fault is theirs.
     */
    public function isConfigured(): bool;

    /** Where the browser goes to authenticate. */
    public function redirect(Request $request): RedirectResponse;

    /**
     * The user the provider asserted, or null if the assertion is not
     * acceptable.
     *
     * Returning null rather than throwing, because "this assertion does not
     * correspond to an account here" is an ordinary outcome and the sign-in
     * screen has to say something useful about it.
     */
    public function user(Request $request): ?User;

    /**
     * Where to send the browser after signing out.
     *
     * Null means stay on this platform. A real bridge usually returns the
     * provider's single-logout endpoint, because signing out of CRASA while
     * remaining signed in to the identity provider is the failure that
     * makes shared machines dangerous.
     */
    public function logoutUrl(): ?string;
}

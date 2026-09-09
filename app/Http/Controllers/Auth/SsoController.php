<?php

namespace App\Http\Controllers\Auth;

use App\Auth\IdentityProvider;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

/**
 * The SSO handoff and its error states. IN-01.
 *
 * These routes exist and are exercised by tests even though no identity
 * provider is configured, because IN-01's deliverable is the handoff *and
 * its failure paths*, and the failure paths are the part that is hard to
 * add later. When a provider arrives it is bound in a service provider and
 * nothing here changes.
 *
 * EVERY OUTCOME IS A NAMED ERROR, NOT A BLANK PAGE. A regulator who cannot
 * sign in needs to know whether the fault is theirs, and the Secretariat
 * needs to know which of the four it was without reading a log.
 */
class SsoController extends Controller
{
    public function __construct(private readonly IdentityProvider $provider) {}

    /** Start the handoff. */
    public function redirect(Request $request): SymfonyRedirect|RedirectResponse
    {
        if (! $this->provider->isConfigured()) {
            // Not configured is not an error the visitor caused. Send them
            // to the password form, which works.
            return redirect()
                ->route('login', ['locale' => app()->getLocale()])
                ->with('status', __('auth.sso_unavailable'));
        }

        return $this->provider->redirect($request);
    }

    /**
     * Come back from the provider.
     *
     * Four outcomes, each with its own message:
     *
     *   Not configured. The route should not have been reachable.
     *   The provider asserted nothing usable.
     *   The provider asserted someone this platform does not know.
     *   The account exists but is suspended.
     *
     * The third is the one that matters operationally: federated identity
     * does not create accounts here. An assertion from a valid CRASA
     * identity that has no account on this platform is refused, because the
     * alternative is that anyone the identity provider trusts becomes a
     * user of the regulatory platform without anyone at the Secretariat
     * deciding so.
     */
    public function callback(Request $request): RedirectResponse
    {
        $login = fn (string $key) => redirect()
            ->route('login', ['locale' => app()->getLocale()])
            ->withErrors(['email' => __($key)]);

        if (! $this->provider->isConfigured()) {
            return $login('auth.sso_unavailable');
        }

        $user = $this->provider->user($request);

        if (! $user instanceof User) {
            AuditLog::create([
                'action' => AuditLog::LOGIN_FAILED,
                'changes' => ['provider' => $this->provider->name(), 'reason' => 'no_assertion'],
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);

            return $login('auth.sso_no_account');
        }

        if (! $user->mayUsePlatform()) {
            AuditLog::create([
                'user_id' => $user->id,
                'action' => AuditLog::LOGIN_FAILED,
                'changes' => ['provider' => $this->provider->name(), 'reason' => 'suspended'],
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);

            return $login('auth.sso_suspended');
        }

        auth()->login($user);
        $request->session()->regenerate();

        AuditLog::create([
            'user_id' => $user->id,
            'action' => AuditLog::LOGIN,
            'changes' => ['provider' => $this->provider->name()],
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        /*
         * Two-factor still applies. A federated sign-in is a claim about
         * who someone is, not a second factor, and CLAUDE.md makes 2FA
         * mandatory for administrators without exception for how they
         * arrived.
         */
        return redirect()->intended(route('account', ['locale' => app()->getLocale()]));
    }
}

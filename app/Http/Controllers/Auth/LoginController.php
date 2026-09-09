<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Enums\Permission;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Screen 11.
 *
 * The failure message never says which of the two was wrong, and reads
 * identically for an address that holds no account, so the form cannot be
 * used to discover who has one. Screen 11 states this explicitly and it is
 * the reason the wording looks vaguer than a normal login.
 *
 * Five attempts, then thirty minutes, keyed on the address and the IP
 * together so one attacker cannot lock a known user out by guessing at them.
 */
class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const LOCKOUT_SECONDS = 1800;

    public function show()
    {
        return view('pages.auth.login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:160'],
            'password' => ['required', 'string'],
        ]);

        $key = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $this->recordFailure($request, $credentials['email'], 'locked');

            throw ValidationException::withMessages([
                'email' => __('auth.locked', [
                    'minutes' => ceil(RateLimiter::availableIn($key) / 60),
                ]),
            ])->redirectTo($this->formUrl());
        }

        $user = User::where('email', $credentials['email'])->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($key, self::LOCKOUT_SECONDS);
            $this->recordFailure($request, $credentials['email'], 'invalid');

            throw ValidationException::withMessages([
                'email' => __('auth.failed', [
                    'remaining' => max(0, self::MAX_ATTEMPTS - RateLimiter::attempts($key)),
                ]),
            ])->redirectTo($this->formUrl());
        }

        RateLimiter::clear($key);

        // Two-factor is a second step, not a second field: the session holds
        // only a pending id until the code is accepted, so a correct password
        // alone never produces an authenticated session.
        if ($user->hasConfirmedTwoFactor()) {
            $request->session()->put('two_factor:pending', $user->id);
            $request->session()->put('two_factor:remember', $request->boolean('remember'));

            return redirect()->route('two-factor.challenge', ['locale' => app()->getLocale()]);
        }

        $this->completeLogin($request, $user);

        return redirect()->intended($this->destinationFor($user));
    }

    public function destroy(Request $request)
    {
        $user = $request->user();

        if ($user !== null) {
            AuditLog::record(AuditLog::LOGOUT, $user, null, $user);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login', ['locale' => app()->getLocale()]);
    }

    /** Shared by the login and the two-factor challenge. */
    public static function completeLogin(Request $request, User $user, bool $remember = false): void
    {
        Auth::login($user, $remember);
        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
            'last_seen_at' => now(),
        ])->save();

        AuditLog::record(AuditLog::LOGIN, $user, [
            'two_factor' => $user->hasConfirmedTwoFactor(),
        ], $user);
    }

    /**
     * Where an account lands after signing in.
     *
     * Everybody used to land on the account page, which is the one screen
     * that tells a reader nothing about what they signed in to do. A
     * member state representative signs in to file a return and a
     * committee member to read papers; putting both in front of a form for
     * changing their own password is asking them to find the platform.
     *
     * The route is a suggestion, not a grant. Every destination below
     * re-checks its own permission on the way in, so an account that
     * cannot reach the back office is redirected out of it rather than let
     * in by having been sent there.
     */
    public static function destinationFor(User $user): string
    {
        $locale = $user->preferred_locale ?: app()->getLocale();

        // An administrator who has not enrolled in two-factor reaches
        // nothing else, so sending them to the dashboard would be one
        // redirect on the way to the enrolment page. Send them there.
        if ($user->role->requiresTwoFactor() && $user->two_factor_confirmed_at === null) {
            return route('two-factor.enrol', ['locale' => $locale]);
        }

        return match (true) {
            $user->hasPermission(Permission::ManageContent) => route('admin.dashboard', ['locale' => $locale]),
            $user->hasPermission(Permission::ReachCommitteeWorkspace) => route('extranet.dashboard', ['locale' => $locale]),
            // A viewer has no workspace. The public site, signed in, is
            // the whole of what they have, and the account page is one
            // click from the header.
            default => route('home', ['locale' => $locale]),
        };
    }

    private function recordFailure(Request $request, string $email, string $reason): void
    {
        // No user id: a failed login may be for an address that holds no
        // account, and the trail must not imply otherwise.
        AuditLog::record(AuditLog::LOGIN_FAILED, null, [
            'email' => $email,
            'reason' => $reason,
        ], null, $request->ip());
    }

    /**
     * Where a failed attempt goes back to.
     *
     * A validation failure redirects back() by default, which needs a
     * referer. A privacy-conscious browser or a proxy may not send one, and
     * then the reader lands on the site root instead of the form they were
     * filling in.
     */
    private function formUrl(): string
    {
        return route('login', ['locale' => app()->getLocale()]);
    }

    private function throttleKey(Request $request): string
    {
        return 'login:'.strtolower($request->input('email')).'|'.$request->ip();
    }
}

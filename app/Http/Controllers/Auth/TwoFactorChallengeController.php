<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * The second step of login. A recovery code is the alternate path for
 * someone who has lost the authenticator.
 */
class TwoFactorChallengeController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(private TwoFactorService $twoFactor) {}

    public function show(Request $request)
    {
        abort_unless($this->pendingUser($request) !== null, 403);

        return view('pages.auth.two-factor-challenge');
    }

    public function store(Request $request)
    {
        $user = $this->pendingUser($request);

        abort_if($user === null, 403);

        $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'mode' => ['nullable', 'in:totp,recovery'],
        ]);

        $key = 'two-factor:'.$user->id.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'code' => __('auth.locked', ['minutes' => ceil(RateLimiter::availableIn($key) / 60)]),
            ])->redirectTo(route('two-factor.challenge', ['locale' => app()->getLocale()]));
        }

        $code = trim($request->string('code')->value());
        $usingRecovery = $request->input('mode') === 'recovery';

        $accepted = $usingRecovery
            ? $this->twoFactor->consumeRecoveryCode($user, $code)
            : $this->twoFactor->verify($user->two_factor_secret, $code);

        if (! $accepted) {
            RateLimiter::hit($key, 900);

            AuditLog::record(AuditLog::TWO_FACTOR_FAILED, $user, [
                'mode' => $usingRecovery ? 'recovery' : 'totp',
            ], $user, $request->ip());

            throw ValidationException::withMessages([
                'code' => $usingRecovery ? __('auth.recovery_invalid') : __('auth.code_invalid'),
            ])->redirectTo(route('two-factor.challenge', ['locale' => app()->getLocale()]));
        }

        RateLimiter::clear($key);

        $remember = (bool) $request->session()->pull('two_factor:remember', false);
        $request->session()->forget('two_factor:pending');

        LoginController::completeLogin($request, $user, $remember);

        return redirect()->intended(LoginController::destinationFor($user));
    }

    private function pendingUser(Request $request): ?User
    {
        $id = $request->session()->get('two_factor:pending');

        return $id === null ? null : User::find($id);
    }
}

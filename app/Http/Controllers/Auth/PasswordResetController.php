<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * Password reset by email.
 *
 * The confirmation is identical whether or not the address holds an account,
 * for the same reason the login failure is: the form must not answer the
 * question "does this person have access".
 */
class PasswordResetController extends Controller
{
    public function request()
    {
        return view('pages.auth.password-request');
    }

    public function email(Request $request)
    {
        $request->validate(['email' => ['required', 'email', 'max:160']]);

        Password::sendResetLink($request->only('email'));

        return back()->with('status', __('auth.reset_sent'));
    }

    public function edit(Request $request, string $token)
    {
        return view('pages.auth.password-reset', [
            'token' => $token,
            'email' => $request->string('email')->value(),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->numbers()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                AuditLog::record(AuditLog::PASSWORD_RESET, $user, null, $user, $request->ip());

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PasswordReset) {
            return back()->withErrors(['email' => __($status)]);
        }

        return redirect()
            ->route('login', ['locale' => app()->getLocale()])
            ->with('status', __('auth.reset_done'));
    }
}

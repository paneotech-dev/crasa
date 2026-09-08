<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Enrolment, per screen 13.
 *
 * The secret is offered as a QR code and as text, because a delegate on a
 * shared device cannot always scan. Recovery codes are shown once, with the
 * warning that they will not be shown again, because they are hashed the
 * moment they are generated and genuinely cannot be retrieved.
 */
class TwoFactorEnrolmentController extends Controller
{
    public function __construct(private TwoFactorService $twoFactor) {}

    public function show(Request $request)
    {
        $user = $request->user();

        if ($user->hasConfirmedTwoFactor()) {
            return view('pages.auth.two-factor-manage', [
                'remaining' => $this->twoFactor->remainingRecoveryCodes($user),
                'codes' => $request->session()->get('recovery_codes'),
            ]);
        }

        // Held in the session, not on the user, until it is confirmed. An
        // unconfirmed secret on the record would look like enrolment.
        $secret = $request->session()->get('two_factor:secret')
            ?? $this->twoFactor->generateSecret();

        $request->session()->put('two_factor:secret', $secret);

        return view('pages.auth.two-factor-enrol', [
            'secret' => $secret,
            'formattedSecret' => $this->twoFactor->formatSecret($secret),
            'qr' => $this->twoFactor->qrCodeSvg($user, $secret),
        ]);
    }

    public function confirm(Request $request)
    {
        $request->validate(['code' => ['required', 'string', 'max:10']]);

        $user = $request->user();
        $secret = $request->session()->get('two_factor:secret');

        abort_if($secret === null, 419);

        if (! $this->twoFactor->verify($secret, trim($request->string('code')->value()))) {
            AuditLog::record(AuditLog::TWO_FACTOR_FAILED, $user, ['stage' => 'enrolment'], $user);

            throw ValidationException::withMessages(['code' => __('auth.code_invalid')]);
        }

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ])->save();

        $codes = $this->twoFactor->regenerateRecoveryCodes($user);
        $request->session()->forget('two_factor:secret');

        AuditLog::record(AuditLog::TWO_FACTOR_ENROLLED, $user, null, $user);

        return redirect()
            ->route('two-factor.enrol', ['locale' => app()->getLocale()])
            ->with('recovery_codes', $codes)
            ->with('just_enrolled', true);
    }

    public function regenerate(Request $request)
    {
        $user = $request->user();

        abort_unless($user->hasConfirmedTwoFactor(), 403);

        $codes = $this->twoFactor->regenerateRecoveryCodes($user);

        AuditLog::record(AuditLog::TWO_FACTOR_ENROLLED, $user, ['action' => 'recovery_codes_regenerated'], $user);

        return redirect()
            ->route('two-factor.enrol', ['locale' => app()->getLocale()])
            ->with('recovery_codes', $codes);
    }

    /**
     * Turning it off. Refused for an administrator: 2FA is mandatory for
     * that role, and an administrator who could switch it off would make it
     * optional in practice.
     */
    public function destroy(Request $request)
    {
        $user = $request->user();

        if ($user->role->requiresTwoFactor()) {
            throw ValidationException::withMessages([
                'two_factor' => __('auth.two_factor_mandatory'),
            ]);
        }

        $this->twoFactor->disable($user);

        AuditLog::record(AuditLog::TWO_FACTOR_DISABLED, $user, null, $user);

        return redirect()->route('account', ['locale' => app()->getLocale()]);
    }
}

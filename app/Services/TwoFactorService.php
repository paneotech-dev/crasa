<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP enrolment, challenge and recovery.
 *
 * The secret is encrypted at rest by the model's cast. Recovery codes are
 * hashed, so they can be verified and never shown twice.
 */
class TwoFactorService
{
    public const RECOVERY_CODE_COUNT = 8;

    public function __construct(private Google2FA $google2fa) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    /**
     * The otpauth URI an authenticator app reads from the QR code. The issuer
     * is what the app shows in its list, so it names the platform rather than
     * the hostname.
     */
    public function provisioningUri(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(
            config('app.name').' CRASA',
            $user->email,
            $secret
        );
    }

    /**
     * An inline SVG QR code. Rendered here rather than fetched from a chart
     * service, because sending a user's TOTP secret to a third party to be
     * drawn would defeat the point of having one.
     */
    public function qrCodeSvg(User $user, string $secret): string
    {
        $writer = new Writer(new ImageRenderer(
            new RendererStyle(200, 0),
            new SvgImageBackEnd
        ));

        return $writer->writeString($this->provisioningUri($user, $secret));
    }

    /** The secret in readable blocks, for an app that cannot scan. */
    public function formatSecret(string $secret): string
    {
        return trim(chunk_split($secret, 4, ' '));
    }

    public function verify(string $secret, string $code): bool
    {
        // One window either side, so a phone clock a few seconds out still
        // works. Wider than that and a stolen code stays usable too long.
        return (bool) $this->google2fa->verifyKey($secret, $code, 1);
    }

    /**
     * Fresh recovery codes. Returns them in clear once, for display, and
     * stores only their hashes.
     *
     * @return list<string>
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        $codes = [];
        $hashes = [];

        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            $code = strtoupper(Str::random(5).'-'.Str::random(5));
            $codes[] = $code;
            $hashes[] = Hash::make($code);
        }

        $user->forceFill(['two_factor_recovery_codes' => json_encode($hashes)])->save();

        return $codes;
    }

    /**
     * Spend a recovery code. Each works once: a used code is removed, so a
     * code read over someone's shoulder is worth nothing after its first use.
     */
    public function consumeRecoveryCode(User $user, string $code): bool
    {
        $hashes = json_decode($user->two_factor_recovery_codes ?? '[]', true) ?: [];

        foreach ($hashes as $index => $hash) {
            if (Hash::check(trim($code), $hash)) {
                unset($hashes[$index]);
                $user->forceFill([
                    'two_factor_recovery_codes' => json_encode(array_values($hashes)),
                ])->save();

                AuditLog::record(AuditLog::RECOVERY_CODE_USED, $user, [
                    'codes_remaining' => count($hashes),
                ], $user);

                return true;
            }
        }

        return false;
    }

    public function remainingRecoveryCodes(User $user): int
    {
        return count(json_decode($user->two_factor_recovery_codes ?? '[]', true) ?: []);
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();
    }
}

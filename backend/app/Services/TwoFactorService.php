<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Stateless TOTP (Google Authenticator) service for the API.
 *
 * Flow:
 *  1. generateSecret()  -> store encrypted on the user (two_factor_secret).
 *  2. qrCodeDataUri()   -> scan with the authenticator app.
 *  3. verify()          -> confirm the first code; store recovery codes.
 */
class TwoFactorService
{
    public function __construct(protected Google2FA $google2fa)
    {
    }

    /**
     * Generate a fresh base32 secret for the user.
     */
    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    public function otpauthUrl(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret,
        );
    }

    /**
     * Render the otpauth URL as an inline SVG data URI (no extra extensions).
     */
    public function qrCodeDataUri(User $user, string $secret): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(200, 0),
            new SvgImageBackEnd(),
        );

        $svg = (new Writer($renderer))->writeString($this->otpauthUrl($user, $secret));

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * Verify a 6-digit code against the user's stored secret.
     */
    public function verify(User $user, string $code, int $window = 1): bool
    {
        $secret = $user->two_factor_secret;

        if (blank($secret)) {
            return false;
        }

        return (bool) $this->google2fa->verifyKey($secret, $code, $window);
    }

    /**
     * @return list<string>
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        return collect(range(1, $count))
            ->map(fn () => Str::random(10).'-'.Str::random(10))
            ->all();
    }
}

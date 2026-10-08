<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorService;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    public function __construct(
        protected TwoFactorService $twoFactor,
        protected AuditLogger $audit,
    ) {
    }

    /**
     * Start 2FA enrolment: generate a secret and return the QR code.
     */
    public function enable(Request $request): JsonResponse
    {
        $user = $request->user();
        $secret = $this->twoFactor->generateSecret();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->audit->log('auth.2fa_enrolment_started', $user);

        return response()->json([
            'secret' => $secret,
            'qr_code' => $this->twoFactor->qrCodeDataUri($user, $secret),
            'otpauth_url' => $this->twoFactor->otpauthUrl($user, $secret),
        ]);
    }

    /**
     * Confirm enrolment with the first generated code; return recovery codes.
     */
    public function confirm(Request $request): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'string']]);
        $user = $request->user();

        if (! $this->twoFactor->verify($user, $validated['code'])) {
            throw ValidationException::withMessages(['code' => ['رمز غير صحيح.']]);
        }

        $codes = $this->twoFactor->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => json_encode($codes),
        ])->save();

        $this->audit->log('auth.2fa_enabled', $user);

        return response()->json([
            'recovery_codes' => $codes,
            'message' => 'تم تفعيل المصادقة الثنائية.',
        ]);
    }

    /**
     * Disable 2FA (requires the account password).
     */
    public function disable(Request $request): JsonResponse
    {
        $validated = $request->validate(['password' => ['required', 'string']]);
        $user = $request->user();

        if (! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => ['كلمة المرور غير صحيحة.']]);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->audit->log('auth.2fa_disabled', $user);

        return response()->json(['message' => 'تم تعطيل المصادقة الثنائية.']);
    }
}

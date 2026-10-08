<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureTwoFactorIsVerified;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthService;
use App\Services\TwoFactorService;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $auth,
        protected TwoFactorService $twoFactor,
        protected AuditLogger $audit,
    ) {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->auth->attempt(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request,
        );

        $device = $request->string('device_name')->toString() ?: 'web';

        // If 2FA is enabled, issue a restricted token and ask for the code.
        if ($user->hasTwoFactorEnabled()) {
            $challengeToken = $user->createToken($device, ['2fa-challenge'])->plainTextToken;

            return response()->json([
                'two_factor_required' => true,
                'challenge_token' => $challengeToken,
                'message' => 'أدخل رمز المصادقة الثنائية.',
            ], 200);
        }

        $token = $this->auth->issueToken($user, $device);

        $this->audit->log('auth.login', $user, description: 'User logged in.');

        return $this->tokenResponse($user, $token);
    }

    public function challenge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! EnsureTwoFactorIsVerified::isChallengeToken($user->currentAccessToken())) {
            abort(403, 'No pending two-factor challenge.');
        }

        $valid = $this->twoFactor->verify($user, $validated['code'])
            || $this->consumeRecoveryCode($user, $validated['code']);

        if (! $valid) {
            $this->audit->log('auth.2fa_failed', $user, description: 'Invalid 2FA code.');

            throw ValidationException::withMessages(['code' => ['رمز غير صحيح.']]);
        }

        // Rotate: drop the challenge token, issue a full token.
        $request->user()->currentAccessToken()->delete();

        $token = $this->auth->issueToken($user, 'web');

        $this->audit->log('auth.2fa_passed', $user, description: 'Two-factor challenge passed.');

        return $this->tokenResponse($user, $token);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => new UserResource($request->user()->load('roles', 'tenant')),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->user()->currentAccessToken()->delete();

        $this->audit->log('auth.logout', $user, description: 'User logged out.');

        return response()->json(['message' => 'تم تسجيل الخروج.']);
    }

    protected function tokenResponse(User $user, string $token): JsonResponse
    {
        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user->load('roles', 'tenant')),
        ]);
    }

    protected function consumeRecoveryCode(User $user, string $code): bool
    {
        $codes = $user->two_factor_recovery_codes ? json_decode($user->two_factor_recovery_codes, true) : [];

        if (! in_array($code, $codes, true)) {
            return false;
        }

        $remaining = array_values(array_diff($codes, [$code]));
        $user->forceFill(['two_factor_recovery_codes' => json_encode($remaining)])->save();

        return true;
    }
}

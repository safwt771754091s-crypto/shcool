<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks access when the current token is a pending 2FA challenge.
 *
 * Login issues a short-lived token with the "2fa-challenge" ability when the
 * user has 2FA enabled. Until the challenge is solved (POST /auth/2fa/challenge)
 * and a full token is issued, every protected route rejects the request.
 */
class EnsureTwoFactorIsVerified
{
    /**
     * A token is a pending 2FA challenge when it holds the dedicated ability
     * and nothing else. The wildcard is excluded explicitly because Sanctum's
     * can() would otherwise treat "*" as satisfying every ability.
     */
    public static function isChallengeToken(?\Laravel\Sanctum\Contracts\HasAbilities $token): bool
    {
        if ($token === null) {
            return false;
        }

        $abilities = $token->abilities ?? [];

        return in_array('2fa-challenge', $abilities, true)
            && ! in_array('*', $abilities, true);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && self::isChallengeToken($user->currentAccessToken())) {
            abort(423, 'Two-factor authentication challenge required.');
        }

        return $next($request);
    }
}

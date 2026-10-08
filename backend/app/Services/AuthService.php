<?php

namespace App\Services;

use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(protected AuditLogger $audit)
    {
    }

    /**
     * Validate credentials and return the user, or throw a validation error.
     */
    public function attempt(string $email, string $password, Request $request): User
    {
        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            $this->audit->log(
                'auth.login_failed',
                description: "Failed login for {$email}",
            );

            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['هذا الحساب معطّل.'],
            ]);
        }

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        return $user;
    }

    /**
     * Issue a Sanctum token. Abilities mirror the user's permissions so the
     * Flutter client can gate UI without a round-trip.
     */
    public function issueToken(User $user, string $deviceName): string
    {
        return $user->createToken($deviceName, $this->abilitiesFor($user))->plainTextToken;
    }

    /**
     * @return list<string>
     */
    protected function abilitiesFor(User $user): array
    {
        if ($user->isPlatformAdmin()) {
            return ['*'];
        }

        return $user->getAllPermissions()
            ->pluck('name')
            ->push('authenticated')
            ->unique()
            ->values()
            ->all();
    }
}

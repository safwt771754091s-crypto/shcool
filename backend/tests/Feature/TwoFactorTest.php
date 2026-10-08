<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PermissionSeeder::class);
    }

    public function test_enrolment_returns_secret_and_qr_code(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/auth/2fa/enable');

        $response->assertOk()
            ->assertJsonStructure(['secret', 'qr_code', 'otpauth_url']);

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $response->json('qr_code'));
        $this->assertNotNull($user->fresh()->two_factor_secret);
    }

    public function test_login_requires_challenge_when_2fa_enabled(): void
    {
        $user = User::factory()->create([
            'email' => 'secured@example.com',
            'password' => bcrypt('secret-password'),
        ]);

        $service = app(TwoFactorService::class);
        $secret = $service->generateSecret();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ])->save();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'secured@example.com',
            'password' => 'secret-password',
        ]);

        $response->assertOk()->assertJsonPath('two_factor_required', true);
        $this->assertNotNull($response->json('challenge_token'));
    }

    public function test_valid_code_completes_the_challenge(): void
    {
        $user = User::factory()->create([
            'email' => 'secured@example.com',
            'password' => bcrypt('secret-password'),
        ]);

        $google2fa = app(Google2FA::class);
        $secret = $google2fa->generateSecretKey(32);
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ])->save();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'secured@example.com',
            'password' => 'secret-password',
        ]);

        $challengeToken = $login->json('challenge_token');
        $code = $google2fa->getCurrentOtp($secret);

        $response = $this->withToken($challengeToken)->postJson('/api/v1/auth/2fa/challenge', [
            'code' => $code,
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'user']);
    }

    public function test_challenge_token_cannot_access_protected_routes(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test', ['2fa-challenge'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/organizations')
            ->assertStatus(423);
    }
}

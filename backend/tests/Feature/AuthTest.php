<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Support\Permission\RoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PermissionSeeder::class);
    }

    public function test_user_can_login_and_receive_a_token(): void
    {
        $school = Organization::factory()->tenant()->create();
        app(RoleProvisioner::class)->provisionTenantRoles($school);

        $user = User::factory()->forTenant($school->id)->create([
            'email' => 'teacher@example.com',
            'password' => bcrypt('secret-password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'teacher@example.com',
            'password' => 'secret-password',
            'device_name' => 'flutter-app',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'email']]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'flutter-app',
        ]);
    }

    public function test_login_returns_the_school_scoped_role(): void
    {
        // Regression: spatie teams scope roles to the active team. On the
        // (unauthenticated) login request the resolver defaulted to the global
        // team, so a school user's role came back as an empty array and the
        // Flutter client could not route to the right portal.
        $school = Organization::factory()->tenant()->create();
        app(RoleProvisioner::class)->provisionTenantRoles($school);

        $user = User::factory()->forTenant($school->id)->create([
            'email' => 'student@example.com',
            'password' => bcrypt('secret-password'),
        ]);
        app(\Spatie\Permission\PermissionRegistrar::class)
            ->setPermissionsTeamId($school->id);
        $user->assignRole(\App\Support\Permission\Roles::STUDENT);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'student@example.com',
            'password' => 'secret-password',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.roles', ['student'])
            ->assertJsonPath('user.tenant_id', $school->id);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['email' => 'x@example.com']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'x@example.com',
            'password' => 'wrong',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'inactive@example.com',
            'is_active' => false,
            'password' => bcrypt('secret-password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'inactive@example.com',
            'password' => 'secret-password',
        ]);

        $response->assertStatus(422);
    }

    public function test_authenticated_user_can_fetch_profile(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/auth/me');

        $response->assertOk()->assertJsonPath('data.email', $user->email);
    }

    public function test_login_is_rate_limited(): void
    {
        User::factory()->create(['email' => 'rl@example.com']);

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'rl@example.com',
                'password' => 'wrong',
            ]);
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'rl@example.com',
            'password' => 'wrong',
        ])->assertStatus(429);
    }
}

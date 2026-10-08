<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PermissionSeeder::class);
    }

    public function test_successful_login_is_audited(): void
    {
        User::factory()->create([
            'email' => 'audit@example.com',
            'password' => bcrypt('secret-password'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'audit@example.com',
            'password' => 'secret-password',
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.login']);
    }

    public function test_failed_login_is_audited(): void
    {
        User::factory()->create(['email' => 'audit@example.com']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'audit@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422);

        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.login_failed']);
    }

    public function test_logout_is_audited(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.logout']);
    }
}

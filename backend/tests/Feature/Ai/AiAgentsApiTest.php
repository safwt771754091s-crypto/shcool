<?php

namespace Tests\Feature\Ai;

use App\Models\Ai\AiConversation;
use App\Models\Organization;
use App\Models\Student\Student;
use App\Models\User;
use App\Support\Permission\RoleProvisioner;
use App\Support\Permission\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AiAgentsApiTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PermissionSeeder::class);

        $this->school = Organization::factory()->tenant()->create();
        app(RoleProvisioner::class)->provisionTenantRoles($this->school);
        $this->actingAsTenant($this->school);
    }

    protected function staff(string $role): User
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->school->id);
        $user = User::factory()->forTenant($this->school->id)->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_index_lists_agents_allowed_for_the_role(): void
    {
        config()->set('ai.api_key', null);

        $response = $this->actingAs($this->staff(Roles::SCHOOL_MANAGER))
            ->getJson('/api/v1/ai');

        $response->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonFragment(['key' => 'manager_assistant']);
    }

    public function test_a_teacher_only_sees_the_teacher_agent(): void
    {
        $response = $this->actingAs($this->staff(Roles::TEACHER))
            ->getJson('/api/v1/ai');

        $response->assertOk();
        $keys = array_column($response->json('data.agents'), 'key');

        $this->assertContains('teacher_assistant', $keys);
        $this->assertNotContains('manager_assistant', $keys);
    }

    public function test_parent_cannot_open_a_manager_conversation(): void
    {
        $this->actingAs($this->staff(Roles::PARENT))
            ->postJson('/api/v1/ai/conversations', ['agent' => 'manager_assistant'])
            ->assertForbidden();
    }

    public function test_full_conversation_flow_with_a_faked_provider(): void
    {
        config()->set('ai.api_key', 'test-key');

        Student::create(['student_number' => 'S-1', 'full_name' => 'طالب', 'status' => Student::STATUS_ENROLLED]);

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'المدرسة بخير.']]],
            ]),
        ]);

        $user = $this->staff(Roles::SCHOOL_MANAGER);

        $conversationId = $this->actingAs($user)
            ->postJson('/api/v1/ai/conversations', ['agent' => 'manager_assistant'])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($user)
            ->postJson("/api/v1/ai/conversations/{$conversationId}/messages", ['message' => 'كيف حال المدرسة؟'])
            ->assertOk()
            ->assertJsonPath('data.reply.content', 'المدرسة بخير.')
            ->assertJsonPath('data.available', true);

        $this->actingAs($user)
            ->getJson("/api/v1/ai/conversations/{$conversationId}")
            ->assertOk()
            ->assertJsonCount(2, 'data.messages');
    }

    public function test_a_user_cannot_read_another_users_conversation(): void
    {
        $owner = $this->staff(Roles::SCHOOL_MANAGER);
        $other = $this->staff(Roles::SCHOOL_MANAGER);

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->school->id);
        $conversation = AiConversation::create([
            'user_id' => $other->getKey(),
            'agent' => 'manager_assistant',
            'last_message_at' => now(),
        ]);

        $this->actingAs($owner)
            ->getJson("/api/v1/ai/conversations/{$conversation->id}")
            ->assertNotFound();
    }

    public function test_conversations_are_isolated_between_schools(): void
    {
        $managerA = $this->staff(Roles::SCHOOL_MANAGER);

        $schoolB = Organization::factory()->tenant()->create();
        app(RoleProvisioner::class)->provisionTenantRoles($schoolB);

        // A conversation in school B, created inside B's tenant context.
        $this->actingAsTenant($schoolB);
        app(PermissionRegistrar::class)->setPermissionsTeamId($schoolB->id);
        $userB = User::factory()->forTenant($schoolB->id)->create();
        $userB->assignRole(Roles::SCHOOL_MANAGER);
        $conversationB = AiConversation::create([
            'user_id' => $userB->getKey(),
            'agent' => 'manager_assistant',
            'last_message_at' => now(),
        ]);

        // Back in school A the manager must not see B's conversation.
        $this->actingAsTenant($this->school);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->school->id);

        $this->actingAs($managerA)
            ->getJson("/api/v1/ai/conversations/{$conversationB->id}")
            ->assertNotFound();
    }
}

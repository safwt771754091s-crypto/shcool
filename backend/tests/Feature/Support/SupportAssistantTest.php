<?php

namespace Tests\Feature\Support;

use App\Models\Organization;
use App\Models\Support\KbArticle;
use App\Models\Support\SupportConversation;
use App\Models\Support\SupportTicket;
use App\Models\User;
use App\Support\Permission\RoleProvisioner;
use App\Support\Permission\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SupportAssistantTest extends TestCase
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

        $this->seedKnowledge();
    }

    protected function seedKnowledge(): void
    {
        KbArticle::create([
            'category' => 'billing',
            'question' => 'كيف أسدد الرسوم الدراسية؟',
            'answer' => 'افتح قائمة الرسوم واختر الفاتورة ثم أتمم الدفع.',
            'keywords' => ['سداد', 'رسوم', 'فاتورة'],
            'published' => true,
        ]);

        KbArticle::create([
            'category' => 'account',
            'question' => 'كيف أعيد تعيين كلمة المرور؟',
            'answer' => 'استخدم رابط نسيت كلمة المرور.',
            'keywords' => ['نسيت', 'استعادة'],
            'published' => true,
        ]);
    }

    protected function staff(string $role): User
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->school->id);
        $user = User::factory()->forTenant($this->school->id)->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_index_lists_intents_and_availability(): void
    {
        config()->set('support.jev.api_key', null);
        config()->set('support.claude.api_key', null);

        $this->actingAs($this->staff(Roles::SCHOOL_MANAGER))
            ->getJson('/api/v1/support/assistant')
            ->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonFragment(['key' => 'billing']);
    }

    public function test_a_user_can_open_a_conversation(): void
    {
        $this->actingAs($this->staff(Roles::SCHOOL_MANAGER))
            ->postJson('/api/v1/support/assistant/conversations', [], ['Accept-Language' => 'ar'])
            ->assertCreated()
            ->assertJsonPath('data.locale', 'ar');
    }

    public function test_a_message_is_answered_from_the_knowledge_base_without_keys(): void
    {
        config()->set('support.jev.api_key', null);
        config()->set('support.claude.api_key', null);

        $user = $this->staff(Roles::SCHOOL_MANAGER);

        $conversationId = $this->actingAs($user)
            ->postJson('/api/v1/support/assistant/conversations')
            ->json('data.id');

        $this->actingAs($user)
            ->postJson("/api/v1/support/assistant/conversations/{$conversationId}/messages", [
                'message' => 'كيف أسدد الرسوم الدراسية؟',
            ])
            ->assertOk()
            ->assertJsonPath('data.intent', 'billing')
            ->assertJsonPath('data.escalate', false)
            ->assertJsonPath('data.reply.meta.provider', 'kb')
            ->assertJsonPath('data.reply.content', 'افتح قائمة الرسوم واختر الفاتورة ثم أتمم الدفع.');
    }

    public function test_asking_for_a_human_escalates_to_a_ticket(): void
    {
        config()->set('support.jev.api_key', null);
        config()->set('support.claude.api_key', null);

        $user = $this->staff(Roles::PARENT);

        $conversationId = $this->actingAs($user)
            ->postJson('/api/v1/support/assistant/conversations')
            ->json('data.id');

        $response = $this->actingAs($user)
            ->postJson("/api/v1/support/assistant/conversations/{$conversationId}/messages", [
                'message' => 'أريد التحدث مع موظف بشري من فضلك',
            ])
            ->assertOk()
            ->assertJsonPath('data.intent', 'handoff')
            ->assertJsonPath('data.escalate', true);

        $this->assertNotNull($response->json('data.ticket.reference'));
        // allTenants(): the tenant context is resolved from the request, which
        // is gone by the time we assert outside the request lifecycle.
        $this->assertSame(1, SupportTicket::allTenants()->count());
    }

    public function test_a_sensitive_topic_always_escalates(): void
    {
        config()->set('support.jev.api_key', null);
        config()->set('support.claude.api_key', null);

        $user = $this->staff(Roles::PARENT);

        $conversationId = $this->actingAs($user)
            ->postJson('/api/v1/support/assistant/conversations')
            ->json('data.id');

        $this->actingAs($user)
            ->postJson("/api/v1/support/assistant/conversations/{$conversationId}/messages", [
                'message' => 'ابني يتعرض للتنمر في المدرسة',
            ])
            ->assertOk()
            ->assertJsonPath('data.escalate', true);
    }

    public function test_pii_is_redacted_before_reaching_claude(): void
    {
        config()->set('support.jev.api_key', null);
        config()->set('support.claude.api_key', 'test-key');

        $captured = null;

        Http::fake(function ($request) use (&$captured) {
            $captured = $request->data();

            return Http::response([
                'model' => 'claude-test',
                'content' => [['type' => 'text', 'text' => 'تم.']],
            ]);
        });

        $user = $this->staff(Roles::SCHOOL_MANAGER);

        $conversationId = $this->actingAs($user)
            ->postJson('/api/v1/support/assistant/conversations')
            ->json('data.id');

        $this->actingAs($user)
            ->postJson("/api/v1/support/assistant/conversations/{$conversationId}/messages", [
                'message' => 'رقمي 0555123456 وبريدي test@example.com، كيف أعيد تعيين كلمة المرور؟',
            ])
            ->assertOk()
            ->assertJsonPath('data.reply.meta.provider', 'claude');

        $payload = json_encode($captured, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('0555123456', $payload);
        $this->assertStringNotContainsString('test@example.com', $payload);
        $this->assertStringContainsString('[محجوب:', $payload);
    }

    public function test_a_user_cannot_read_another_users_conversation(): void
    {
        $owner = $this->staff(Roles::SCHOOL_MANAGER);
        $other = $this->staff(Roles::SCHOOL_MANAGER);

        $conversation = SupportConversation::create([
            'user_id' => $other->getKey(),
            'last_message_at' => now(),
        ]);

        $this->actingAs($owner)
            ->getJson("/api/v1/support/assistant/conversations/{$conversation->id}")
            ->assertNotFound();
    }

    public function test_conversations_are_isolated_between_schools(): void
    {
        $this->staff(Roles::SCHOOL_MANAGER);

        $schoolB = Organization::factory()->tenant()->create();
        app(RoleProvisioner::class)->provisionTenantRoles($schoolB);

        $this->actingAsTenant($schoolB);
        app(PermissionRegistrar::class)->setPermissionsTeamId($schoolB->id);
        $userB = User::factory()->forTenant($schoolB->id)->create();
        $userB->assignRole(Roles::SCHOOL_MANAGER);
        $conversationB = SupportConversation::create([
            'user_id' => $userB->getKey(),
            'last_message_at' => now(),
        ]);

        $this->actingAsTenant($this->school);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->school->id);

        $this->actingAs($this->staff(Roles::SCHOOL_MANAGER))
            ->getJson("/api/v1/support/assistant/conversations/{$conversationB->id}")
            ->assertNotFound();
    }
}

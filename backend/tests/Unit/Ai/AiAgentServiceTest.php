<?php

namespace Tests\Unit\Ai;

use App\Models\Ai\AiConversation;
use App\Models\Organization;
use App\Models\Student\Student;
use App\Models\User;
use App\Services\Ai\AiAgentService;
use App\Support\Permission\RoleProvisioner;
use App\Support\Permission\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AiAgentServiceTest extends TestCase
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

    protected function manager(): User
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->school->id);
        $user = User::factory()->forTenant($this->school->id)->create();
        $user->assignRole(Roles::SCHOOL_MANAGER);

        return $user;
    }

    protected function conversation(User $user, string $agent = 'manager_assistant'): AiConversation
    {
        return AiConversation::create([
            'user_id' => $user->getKey(),
            'agent' => $agent,
            'last_message_at' => now(),
        ]);
    }

    public function test_respond_returns_model_answer_without_tools(): void
    {
        config()->set('ai.api_key', 'test-key');

        Http::fake([
            '*/chat/completions' => Http::response([
                'model' => 'test-model',
                'choices' => [['message' => ['content' => 'مرحباً، كيف أساعدك؟']]],
                'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 7],
            ]),
        ]);

        $user = $this->manager();
        $result = app(AiAgentService::class)->respond($this->conversation($user), $user, 'السلام عليكم');

        $this->assertSame('مرحباً، كيف أساعدك؟', $result['message']->content);
        $this->assertSame([], $result['tools_used']);
        $this->assertNull($result['notice']);
        $this->assertSame('test-model', $result['message']->model);
        $this->assertDatabaseHas('ai_messages', ['role' => 'user', 'content' => 'السلام عليكم']);
    }

    public function test_respond_executes_tool_calls_then_answers(): void
    {
        config()->set('ai.api_key', 'test-key');

        Student::create(['student_number' => 'S-1', 'full_name' => 'طالب', 'status' => Student::STATUS_ENROLLED]);

        Http::fake([
            '*/chat/completions' => Http::sequence()
                ->push([
                    'choices' => [['message' => [
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_1',
                            'type' => 'function',
                            'function' => ['name' => 'student_count', 'arguments' => '{}'],
                        ]],
                    ]]],
                ])
                ->push([
                    'choices' => [['message' => ['content' => 'لديك طالب واحد.']]],
                ]),
        ]);

        $user = $this->manager();
        $result = app(AiAgentService::class)->respond($this->conversation($user), $user, 'كم عدد الطلاب؟');

        $this->assertSame('لديك طالب واحد.', $result['message']->content);
        $this->assertContains('student_count', $result['tools_used']);

        // The tool result (total = 1) must have been sent back to the model.
        Http::assertSent(function ($request) {
            $messages = $request['messages'] ?? [];
            foreach ($messages as $message) {
                if (($message['role'] ?? null) === 'tool') {
                    return str_contains($message['content'] ?? '', '"total":1');
                }
            }

            return false;
        });
    }

    public function test_respond_degrades_gracefully_when_provider_is_missing(): void
    {
        config()->set('ai.api_key', null);

        Http::fake();

        $user = $this->manager();
        $result = app(AiAgentService::class)->respond($this->conversation($user), $user, 'سؤال');

        $this->assertNotNull($result['notice']);
        $this->assertStringContainsString('غير مُهيّأة', $result['message']->content);
        Http::assertNothingSent();
    }

    public function test_a_denied_tool_call_is_not_reported_as_used(): void
    {
        config()->set('ai.api_key', 'test-key');

        // The model insists on a school-wide tool the parent may not call.
        Http::fake([
            '*/chat/completions' => Http::sequence()
                ->push(['choices' => [['message' => [
                    'content' => null,
                    'tool_calls' => [[
                        'id' => 'call_x',
                        'type' => 'function',
                        'function' => ['name' => 'student_count', 'arguments' => '{}'],
                    ]],
                ]]]])
                ->push(['choices' => [['message' => ['content' => 'لا أستطيع الوصول لهذه البيانات.']]]]),
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->school->id);
        $parent = User::factory()->forTenant($this->school->id)->create();
        $parent->assignRole(Roles::PARENT);

        $result = app(AiAgentService::class)->respond(
            $this->conversation($parent, 'parent_assistant'),
            $parent,
            'كم عدد الطلاب؟',
        );

        $this->assertSame([], $result['tools_used']);

        // The model must have received an error, not real school figures.
        Http::assertSent(function ($request) {
            foreach ($request['messages'] ?? [] as $message) {
                if (($message['role'] ?? null) === 'tool') {
                    return array_key_exists('error', json_decode($message['content'], true) ?? []);
                }
            }

            return false;
        });
    }

    public function test_history_is_replayed_to_the_model(): void
    {
        config()->set('ai.api_key', 'test-key');

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'تمام']]],
            ]),
        ]);

        $user = $this->manager();
        $conversation = $this->conversation($user);
        $service = app(AiAgentService::class);

        $service->respond($conversation, $user, 'السؤال الأول');
        $service->respond($conversation, $user, 'السؤال الثاني');

        Http::assertSent(function ($request) {
            $contents = array_column($request['messages'] ?? [], 'content');

            return in_array('السؤال الأول', $contents, true)
                && in_array('السؤال الثاني', $contents, true);
        });
    }
}

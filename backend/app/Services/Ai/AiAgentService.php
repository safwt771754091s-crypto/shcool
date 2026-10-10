<?php

namespace App\Services\Ai;

use App\Models\Ai\AiConversation;
use App\Models\Ai\AiMessage;
use App\Models\User;
use RuntimeException;

/**
 * Orchestrates one turn of an agent conversation.
 *
 * Flow: replay recent history -> call the model with the agent's tools ->
 * execute any tool calls (read-only, tenant-scoped) -> feed the results back ->
 * repeat until the model answers or we hit the step limit.
 *
 * If the provider is not configured the caller gets a friendly Arabic notice
 * instead of an exception, so the feature is safe to ship without keys.
 */
class AiAgentService
{
    public function __construct(
        protected AiService $ai,
        protected AiToolbox $toolbox,
        protected AiAgentFactory $agents,
    ) {}

    public function isAvailable(): bool
    {
        return $this->ai->isConfigured();
    }

    /**
     * Run one user message against the conversation's agent.
     *
     * @return array{message: AiMessage, tools_used: list<string>, notice: ?string}
     */
    public function respond(AiConversation $conversation, User $user, string $userMessage): array
    {
        $userTurn = AiMessage::create([
            'conversation_id' => $conversation->getKey(),
            'role' => AiMessage::ROLE_USER,
            'content' => $userMessage,
        ]);

        $conversation->forceFill([
            'last_message_at' => now(),
            'title' => $conversation->title ?: mb_substr($userMessage, 0, 60),
        ])->save();

        $agent = $this->agents->find($conversation->agent);

        if ($agent === null) {
            return $this->fallback($conversation, 'الوكيل المطلوب غير معروف.');
        }

        if (! $this->ai->isConfigured()) {
            return $this->fallback(
                $conversation,
                'ميزة الذكاء الاصطناعي غير مُهيّأة بعد. يجب على مدير المنصة ضبط مفتاح المزوّد (AI_API_KEY).',
            );
        }

        $messages = $this->buildMessages($conversation, $agent, $user);
        $tools = $this->toolbox->schemas($agent['tools'] ?? [], $user);
        $maxSteps = max(1, (int) config('ai.max_steps', 4));
        $toolsUsed = [];

        try {
            for ($step = 0; $step < $maxSteps; $step++) {
                $response = $this->ai->chat($messages, $tools);

                $toolCalls = $response['tool_calls'] ?? [];
                $content = $response['content'] ?? null;

                if ($toolCalls === []) {
                    return [
                        'message' => $this->storeAssistant($conversation, $content ?? '', $response),
                        'tools_used' => $toolsUsed,
                        'notice' => null,
                    ];
                }

                // Record the assistant's tool request, then run each tool.
                $messages[] = [
                    'role' => 'assistant',
                    'content' => $content,
                    'tool_calls' => $toolCalls,
                ];

                foreach ($toolCalls as $call) {
                    $name = $call['function']['name'] ?? '';
                    $args = $this->decodeArguments($call['function']['arguments'] ?? '{}');

                    // Denied calls are still fed back to the model as an error,
                    // but they are never reported as "used".
                    $allowed = $this->toolbox->allows($name, $user);
                    $result = $this->toolbox->run($name, $args, $user);

                    if ($allowed) {
                        $toolsUsed[] = $name;
                    }

                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $call['id'] ?? $name,
                        'name' => $name,
                        'content' => json_encode($result, JSON_UNESCAPED_UNICODE),
                    ];
                }
            }
        } catch (RuntimeException $e) {
            report($e);

            return $this->fallback($conversation, 'تعذّر الوصول إلى خدمة الذكاء الاصطناعي حالياً.');
        }

        // Step limit reached without a final answer: ask for a summary.
        $messages[] = [
            'role' => 'system',
            'content' => 'لخّص الآن إجابة نهائية موجزة بالعربية لمستخدم بناءً على نتائج الأدوات أعلاه.',
        ];

        $response = $this->ai->chat($messages, []);

        return [
            'message' => $this->storeAssistant($conversation, $response['content'] ?? '', $response, $toolsUsed),
            'tools_used' => $toolsUsed,
            'notice' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $agent
     * @return list<array<string, mixed>>
     */
    protected function buildMessages(AiConversation $conversation, array $agent, User $user): array
    {
        $messages = [[
            'role' => 'system',
            'content' => ($agent['system'] ?? '')."\n\n"
                ."المستخدم الحالي: {$user->name} (الدور: ".implode('، ', $user->getRoleNames()->all()).").",
        ]];

        $history = $conversation->messages()
            ->whereIn('role', [AiMessage::ROLE_USER, AiMessage::ROLE_ASSISTANT])
            ->orderByDesc('id')
            ->limit((int) config('ai.history_limit', 20))
            ->get()
            ->reverse();

        foreach ($history as $message) {
            if (filled($message->content)) {
                $messages[] = ['role' => $message->role, 'content' => $message->content];
            }
        }

        return $messages;
    }

    /**
     * @param  array<string, mixed>  $response
     * @param  list<string>  $toolsUsed
     */
    protected function storeAssistant(AiConversation $conversation, string $content, array $response, array $toolsUsed = []): AiMessage
    {
        return AiMessage::create([
            'conversation_id' => $conversation->getKey(),
            'role' => AiMessage::ROLE_ASSISTANT,
            'content' => $content,
            'tool_results' => $toolsUsed === [] ? null : ['used' => $toolsUsed],
            'model' => $response['model'] ?? $this->ai->model(),
            'prompt_tokens' => $response['usage']['prompt_tokens'] ?? 0,
            'completion_tokens' => $response['usage']['completion_tokens'] ?? 0,
        ]);
    }

    /**
     * @return array{message: AiMessage, tools_used: list<string>, notice: ?string}
     */
    protected function fallback(AiConversation $conversation, string $notice): array
    {
        $message = AiMessage::create([
            'conversation_id' => $conversation->getKey(),
            'role' => AiMessage::ROLE_ASSISTANT,
            'content' => $notice,
            'model' => null,
        ]);

        return ['message' => $message, 'tools_used' => [], 'notice' => $notice];
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeArguments(mixed $arguments): array
    {
        if (is_array($arguments)) {
            return $arguments;
        }

        if (is_string($arguments) && $arguments !== '') {
            $decoded = json_decode($arguments, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}

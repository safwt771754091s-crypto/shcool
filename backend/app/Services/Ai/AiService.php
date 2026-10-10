<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Provider-agnostic LLM client.
 *
 * Talks the OpenAI-compatible /chat/completions wire format, which is spoken by
 * OpenAI, Groq, Together, OpenRouter, Fireworks, vLLM, Ollama and most others.
 * Only the base URL, key and model change between providers.
 *
 * When no key is configured the service reports itself unavailable and refuses
 * to call out; callers use isConfigured() to degrade gracefully.
 */
class AiService
{
    public function __construct(
        protected ?string $baseUrl = null,
        protected ?string $apiKey = null,
        protected ?string $model = null,
        protected ?int $timeout = null,
    ) {
        $config = config('ai', []);

        $this->baseUrl = rtrim($baseUrl ?? ($config['base_url'] ?? 'https://api.openai.com/v1'), '/');
        $this->apiKey = $apiKey ?? ($config['api_key'] ?? null);
        $this->model = $model ?? ($config['model'] ?? 'gpt-4o-mini');
        $this->timeout = $timeout ?? (int) ($config['timeout'] ?? 60);
    }

    public function isConfigured(): bool
    {
        return (bool) config('ai.enabled', true) && filled($this->apiKey);
    }

    public function model(): string
    {
        return (string) $this->model;
    }

    /**
     * Send a chat completion request.
     *
     * @param  list<array<string, mixed>>  $messages  OpenAI-style messages
     * @param  list<array<string, mixed>>  $tools  OpenAI-style tool definitions
     * @return array{content: ?string, tool_calls: list<array<string, mixed>>, model: ?string, usage: array<string, int>}
     */
    public function chat(array $messages, array $tools = []): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('AI provider is not configured.');
        }

        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => 0.2,
        ];

        if ($tools !== []) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        $response = Http::withToken($this->apiKey)
            ->acceptJson()
            ->timeout($this->timeout)
            ->post($this->baseUrl.'/chat/completions', $payload);

        if ($response->failed()) {
            throw new RuntimeException(
                'AI provider error: HTTP '.$response->status().' '.$response->body()
            );
        }

        $message = $response->json('choices.0.message') ?? [];

        return [
            'content' => $message['content'] ?? null,
            'tool_calls' => $message['tool_calls'] ?? [],
            'model' => $response->json('model', $this->model),
            'usage' => [
                'prompt_tokens' => (int) $response->json('usage.prompt_tokens', 0),
                'completion_tokens' => (int) $response->json('usage.completion_tokens', 0),
            ],
        ];
    }
}

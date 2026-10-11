<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Cache;
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

    /**
     * Resolve the API key, transparently fetching a short-lived one when the
     * gateway is configured that way (AI_KEY_REFRESH_URL). Static keys win.
     */
    public function key(): ?string
    {
        if (filled($this->apiKey)) {
            return $this->apiKey;
        }

        $refreshUrl = config('ai.key_refresh_url');

        if (blank($refreshUrl)) {
            return null;
        }

        return Cache::remember(
            'ai.managed_key',
            max(30, (int) config('ai.key_ttl', 600)),
            function () use ($refreshUrl) {
                $request = Http::acceptJson()->timeout(10);
                $secret = config('ai.key_refresh_secret');

                if (filled($secret)) {
                    $request = $request->withHeaders([
                        (string) config('ai.key_refresh_header', 'X-Session-API-Key') => $secret,
                    ]);
                }

                $response = $request->get($refreshUrl);

                if ($response->failed()) {
                    throw new RuntimeException(
                        'AI key refresh failed: HTTP '.$response->status()
                    );
                }

                return trim($response->body());
            }
        );
    }

    public function isConfigured(): bool
    {
        if (! (bool) config('ai.enabled', true)) {
            return false;
        }

        try {
            return filled($this->key());
        } catch (RuntimeException) {
            // A gateway/key-refresh outage must never break the endpoint; the
            // caller degrades to the "not configured" notice instead.
            return false;
        }
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

        $response = Http::withToken($this->key())
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

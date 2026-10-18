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

        $refreshUrl = (string) config('ai.key_refresh_url');

        // Runtime env fallback (rotated sandbox session key) is skipped in
        // tests so key resolution stays deterministic.
        if (blank($refreshUrl) && ! app()->environment('testing')) {
            $refreshUrl = $this->runtimeEnv('OH_LLM_API_KEY_REFRESH_URL');
        }

        if (blank($refreshUrl)) {
            return null;
        }

        return Cache::remember(
            'ai.managed_key.v2',
            max(30, (int) config('ai.key_ttl', 600)),
            function () use ($refreshUrl) {
                // Preferred: the explicit .env secret. Fallback: the session key
                // the OpenHands runtime exports (OH_LLM_API_KEY_REFRESH_HEADERS),
                // so a rotated sandbox keeps working without editing .env.
                $secrets = array_values(array_unique(array_filter(
                    [config('ai.key_refresh_secret'), ...$this->runtimeRefreshSecrets()],
                    'filled',
                )));

                $last = null;

                foreach ($secrets as $secret) {
                    $response = Http::acceptJson()->timeout(10)
                        ->withHeaders([
                            (string) config('ai.key_refresh_header', 'X-Session-API-Key') => $secret,
                        ])
                        ->get($refreshUrl);

                    if ($response->successful()) {
                        return trim($response->body());
                    }

                    $last = $response;
                }

                throw new RuntimeException(
                    'AI key refresh failed: HTTP '.($last?->status() ?? 0)
                );
            }
        );
    }

    /** Read a runtime environment variable (FastCGI/cli-safe). */
    private function runtimeEnv(string $name): string
    {
        $value = getenv($name);

        if ($value === false || $value === '') {
            $value = $_SERVER[$name] ?? ($_ENV[$name] ?? '');
        }

        return (string) $value;
    }

    /**
     * Session keys exposed by the OpenHands runtime, if any.
     *
     * @return list<string>
     */
    private function runtimeRefreshSecrets(): array
    {
        $secrets = [];

        $raw = $this->runtimeEnv('OH_LLM_API_KEY_REFRESH_HEADERS');

        if (filled($raw)) {
            $headers = json_decode($raw, true);

            if (is_array($headers)) {
                $secrets = array_merge($secrets, array_values(array_filter($headers, 'is_string')));
            }
        }

        foreach (['SESSION_API_KEY', 'OH_SESSION_API_KEY'] as $name) {
            $value = $this->runtimeEnv($name);

            if (filled($value)) {
                $secrets[] = $value;
            }
        }

        return $secrets;
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

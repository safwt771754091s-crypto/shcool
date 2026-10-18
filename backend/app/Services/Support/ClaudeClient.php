<?php

namespace App\Services\Support;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimal client for the Anthropic Messages API (Claude).
 *
 * Used only to draft a grounded, Arabic-first support reply from the retrieved
 * knowledge passages. It is told, in the system prompt, to answer strictly from
 * those passages and to say so when it does not know — the service still guards
 * the output (citations are attached, not invented).
 *
 * @see https://docs.anthropic.com/en/api/messages
 */
class ClaudeClient
{
    public function isConfigured(): bool
    {
        return filled(config('support.claude.api_key'));
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @return array{content: string, model: ?string, usage: array<string, int>}
     */
    public function complete(string $system, array $messages): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Claude is not configured.');
        }

        $payload = [
            'model' => config('support.claude.model', 'claude-sonnet-4-5'),
            'max_tokens' => (int) config('support.claude.max_tokens', 1024),
            'system' => $system,
            'messages' => array_map(fn ($m) => [
                'role' => $m['role'] === 'assistant' ? 'assistant' : 'user',
                'content' => $m['content'],
            ], $messages),
        ];

        $response = Http::withHeaders([
            'x-api-key' => config('support.claude.api_key'),
            'anthropic-version' => config('support.claude.version', '2023-06-01'),
        ])
            ->acceptJson()
            ->timeout((int) config('support.claude.timeout', 60))
            ->post(rtrim((string) config('support.claude.base_url'), '/').'/messages', $payload);

        if ($response->failed()) {
            throw new RuntimeException('Claude error: HTTP '.$response->status().' '.$response->body());
        }

        $content = collect($response->json('content', []))
            ->where('type', 'text')
            ->pluck('text')
            ->implode("\n");

        return [
            'content' => trim($content),
            'model' => $response->json('model'),
            'usage' => [
                'input_tokens' => (int) $response->json('usage.input_tokens', 0),
                'output_tokens' => (int) $response->json('usage.output_tokens', 0),
            ],
        ];
    }
}

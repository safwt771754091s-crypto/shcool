<?php

namespace App\Services\Support;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for Jev (TypeSafe System One), the intent classifier.
 *
 * Jev answers a typed `choice` question with one of the criteria keys plus a
 * calibrated confidence — no free-text parsing. We use one question, "intent",
 * whose criteria are the configured intents. When the key is missing the caller
 * degrades to the keyword classifier (SupportAssistantService).
 *
 * @see https://jev.is/docs — POST {base_url}/systemone
 */
class JevClient
{
    public function isConfigured(): bool
    {
        return filled(config('support.jev.api_key'));
    }

    /**
     * Classify a customer message into one of the configured intents.
     *
     * @param  list<string>  $intents
     * @return array{intent: ?string, confidence: float, alternatives: array<string, float>, model: ?string}|null
     */
    public function classify(string $text, array $intents, string $locale = 'ar'): ?array
    {
        if (! $this->isConfigured() || $intents === []) {
            return null;
        }

        $catalogue = config('support.intents', []);
        $criteria = [];

        foreach ($intents as $intent) {
            $criteria[$intent] = $catalogue[$intent]['label'] ?? $intent;
        }

        $payload = [
            'model' => config('support.jev.model', 'jev-latest'),
            'state' => $text,
            'questions' => [
                'intent' => [
                    'type' => 'choice',
                    'instructions' => $locale === 'ar'
                        ? 'صنّف رسالة العميل إلى الفئة الأنسب من الخيارات المتاحة.'
                        : 'Classify the customer message into the most fitting option.',
                    'criteria' => $criteria,
                ],
            ],
        ];

        $response = Http::withToken(config('support.jev.api_key'))
            ->acceptJson()
            ->timeout((int) config('support.jev.timeout', 20))
            ->post(rtrim((string) config('support.jev.base_url'), '/').'/systemone', $payload);

        if ($response->failed()) {
            throw new RuntimeException('Jev error: HTTP '.$response->status().' '.$response->body());
        }

        $answer = $response->json('answers.intent') ?? [];

        $probabilities = collect($answer['probabilities'] ?? [])
            ->map(fn ($v) => (float) $v)
            ->all();

        return [
            'intent' => $answer['choice'] ?? null,
            'confidence' => (float) ($answer['confidence'] ?? 0.0),
            'alternatives' => $probabilities,
            'model' => $response->json('model'),
        ];
    }
}

<?php

namespace App\Services\Notifications\Channels;

use App\Models\Notification\NotificationLog;
use App\Services\Notifications\Contracts\NotificationChannel;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * WhatsApp Business Cloud API transport (Meta Graph).
 *
 * Sends a template message when a template name is configured on the log's
 * meta, otherwise a plain text message. Credentials live in services.whatsapp.
 */
class WhatsAppChannel implements NotificationChannel
{
    protected ?string $token;

    protected ?string $phoneNumberId;

    protected ?string $apiVersion;

    protected ?string $baseUrl;

    public function __construct(
        ?string $token = null,
        ?string $phoneNumberId = null,
        ?string $apiVersion = null,
        ?string $baseUrl = null,
    ) {
        $config = config('services.whatsapp', []);

        $this->token = $token ?? ($config['token'] ?? null);
        $this->phoneNumberId = $phoneNumberId ?? ($config['phone_number_id'] ?? null);
        $this->apiVersion = $apiVersion ?? ($config['api_version'] ?? 'v20.0');
        $this->baseUrl = $baseUrl ?? ($config['base_url'] ?? 'https://graph.facebook.com');
    }

    public function name(): string
    {
        return 'whatsapp';
    }

    public function isConfigured(): bool
    {
        return filled($this->token) && filled($this->phoneNumberId);
    }

    public function send(NotificationLog $log): ?string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('WhatsApp Business API is not configured.');
        }

        $url = sprintf(
            '%s/%s/%s/messages',
            rtrim((string) $this->baseUrl, '/'),
            $this->apiVersion,
            $this->phoneNumberId,
        );

        $template = $log->meta['whatsapp_template'] ?? null;

        if (filled($template)) {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $log->recipient,
                'type' => 'template',
                'template' => [
                    'name' => $template,
                    'language' => ['code' => $log->meta['whatsapp_language'] ?? 'ar'],
                    'components' => $log->meta['whatsapp_components'] ?? [],
                ],
            ];
        } else {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $log->recipient,
                'type' => 'text',
                'text' => ['preview_url' => false, 'body' => $log->body],
            ];
        }

        $response = Http::withToken($this->token)
            ->timeout(15)
            ->post($url, $payload);

        if ($response->failed()) {
            throw new RuntimeException(
                'WhatsApp API rejected the message: HTTP '.$response->status().' '.$response->body()
            );
        }

        return $response->json('messages.0.id');
    }
}

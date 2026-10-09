<?php

namespace App\Services\Notifications\Channels;

use App\Models\Notification\NotificationLog;
use App\Services\Notifications\Contracts\NotificationChannel;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * SMS transport.
 *
 * Uses a generic HTTP gateway (Twilio-compatible by default) so any provider
 * with an HTTP API can be wired in via configuration. When no credentials are
 * present the channel reports itself unavailable and the platform records the
 * message as queued rather than pretending it was delivered.
 */
class SmsChannel implements NotificationChannel
{
    public function __construct(
        protected ?string $endpoint = null,
        protected ?string $username = null,
        protected ?string $password = null,
        protected ?string $sender = null,
    ) {
        $config = config('services.sms', []);

        $this->endpoint = $endpoint ?? ($config['endpoint'] ?? null);
        $this->username = $username ?? ($config['username'] ?? null);
        $this->password = $password ?? ($config['password'] ?? null);
        $this->sender = $sender ?? ($config['sender'] ?? null);
    }

    public function name(): string
    {
        return 'sms';
    }

    public function isConfigured(): bool
    {
        return filled($this->endpoint) && filled($this->username) && filled($this->password);
    }

    public function send(NotificationLog $log): ?string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('SMS gateway is not configured.');
        }

        $payload = [
            'to' => $log->recipient,
            'from' => $this->sender,
            'message' => $log->body,
        ];

        $response = Http::asForm()
            ->withBasicAuth($this->username, $this->password)
            ->timeout(15)
            ->post($this->endpoint, $payload);

        if ($response->failed()) {
            throw new RuntimeException(
                'SMS gateway rejected the message: HTTP '.$response->status().' '.$response->body()
            );
        }

        return $response->json('sid')
            ?? $response->json('message_id')
            ?? $response->json('id');
    }
}

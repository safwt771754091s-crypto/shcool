<?php

namespace App\Services\Notifications\Contracts;

use App\Models\Notification\NotificationLog;

/**
 * A delivery transport (SMS gateway, WhatsApp Business API, ...).
 */
interface NotificationChannel
{
    /**
     * Machine name stored on the log row, e.g. "sms" or "whatsapp".
     */
    public function name(): string;

    /**
     * Whether the transport has the credentials it needs to send. Channels
     * without credentials stay registered but report unavailable, so the rest
     * of the platform keeps working until the operator configures them.
     */
    public function isConfigured(): bool;

    /**
     * Deliver the message and return the provider's message id, or null when
     * the provider does not return one.
     *
     * @throws \RuntimeException when delivery fails.
     */
    public function send(NotificationLog $log): ?string;
}

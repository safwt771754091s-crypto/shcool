<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * A generic in-app notification payload. It carries a title, a body and the
 * originating template key; the mobile/web client renders it directly.
 */
class PlatformNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $title,
        public string $body,
        public ?string $templateKey = null,
        public array $data = [],
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return array_merge($this->data, [
            'title' => $this->title,
            'body' => $this->body,
            'template_key' => $this->templateKey,
            'channel' => 'in_app',
        ]);
    }
}

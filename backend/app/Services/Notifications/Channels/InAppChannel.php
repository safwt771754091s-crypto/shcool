<?php

namespace App\Services\Notifications\Channels;

use App\Models\Notification\NotificationLog;
use App\Models\User;
use App\Notifications\PlatformNotification;
use App\Services\Notifications\Contracts\NotificationChannel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * In-app notification (إشعار داخل التطبيق).
 *
 * Writes to Laravel's standard `notifications` table so the existing
 * Notifiable user model exposes it through the usual API. No external
 * credentials are required, so this channel is always available.
 */
class InAppChannel implements NotificationChannel
{
    public function name(): string
    {
        return 'in_app';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function send(NotificationLog $log): ?string
    {
        $id = (string) Str::uuid();

        DB::table('notifications')->insert([
            'id' => $id,
            'type' => PlatformNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $log->user_id,
            'data' => json_encode([
                'title' => $log->subject,
                'body' => $log->body,
                'template_key' => $log->template_key,
                'channel' => 'in_app',
            ], JSON_UNESCAPED_UNICODE),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}

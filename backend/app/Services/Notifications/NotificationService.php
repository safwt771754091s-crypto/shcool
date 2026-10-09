<?php

namespace App\Services\Notifications;

use App\Jobs\SendNotificationJob;
use App\Models\Notification\NotificationLog;
use App\Models\Notification\NotificationPreference;
use App\Models\Notification\NotificationTemplate;
use App\Models\User;
use App\Support\Tenancy\TenantManager;

/**
 * Orchestrates outbound notifications (إدارة الإشعارات).
 *
 * Responsibilities:
 *  - resolve a template for the key/channel/locale (tenant copy first, then the
 *    platform default, then a built-in fallback),
 *  - render placeholders,
 *  - respect the recipient's channel preferences,
 *  - persist a NotificationLog row and hand delivery to a queued job.
 */
class NotificationService
{
    public function __construct(
        protected NotificationChannelManager $channels,
        protected TenantManager $tenants,
    ) {}

    /**
     * Queue a templated message to a user across the given channels.
     *
     * @param  list<string>|null  $channels  defaults to every available channel
     * @param  array<string, mixed>  $data  placeholder values
     * @param  array<string, mixed>  $meta  provider options (e.g. whatsapp_template)
     * @return list<NotificationLog>
     */
    public function sendToUser(
        User $user,
        string $key,
        array $data = [],
        ?array $channels = null,
        array $meta = [],
    ): array {
        $channels = $channels ?? $this->defaultChannels();

        $logs = [];

        foreach ($channels as $channel) {
            if (! $this->channels->has($channel)) {
                continue;
            }

            if (! $this->userAllows($user, $channel)) {
                continue;
            }

            $recipient = $this->recipientFor($user, $channel);

            if ($recipient === null) {
                continue;
            }

            $logs[] = $this->queue($channel, $recipient, $key, $data, $user, $meta);
        }

        return $logs;
    }

    /**
     * Queue a templated message to a raw phone number (no account required).
     *
     * @param  list<string>|null  $channels
     * @param  array<string, mixed>  $data
     * @return list<NotificationLog>
     */
    public function sendToPhone(
        string $phone,
        string $key,
        array $data = [],
        ?array $channels = null,
        array $meta = [],
    ): array {
        $channels = $channels ?? array_values(array_intersect($this->defaultChannels(), ['sms', 'whatsapp']));

        $logs = [];

        foreach ($channels as $channel) {
            if (! $this->channels->has($channel)) {
                continue;
            }

            $logs[] = $this->queue($channel, $phone, $key, $data, null, $meta);
        }

        return $logs;
    }

    /**
     * Create the log row and dispatch the delivery job.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $meta
     */
    public function queue(
        string $channel,
        string $recipient,
        string $key,
        array $data = [],
        ?User $user = null,
        array $meta = [],
    ): NotificationLog {
        [$title, $body] = $this->render($key, $channel, $data);

        $log = NotificationLog::create([
            'tenant_id' => $this->tenants->tenantId(),
            'user_id' => $user?->getKey(),
            'channel' => $channel,
            'recipient' => $recipient,
            'template_key' => $key,
            'subject' => $title,
            'body' => $body,
            'status' => NotificationLog::STATUS_QUEUED,
            'meta' => $meta ?: null,
            'queued_at' => now(),
        ]);

        SendNotificationJob::dispatch($log->getKey());

        return $log;
    }

    /**
     * Deliver a queued log synchronously (used by the job and by tests).
     */
    public function deliver(NotificationLog $log): NotificationLog
    {
        $log->forceFill([
            'status' => NotificationLog::STATUS_SENDING,
            'attempts' => $log->attempts + 1,
        ])->save();

        try {
            $provider = $this->channels->channel($log->channel);
            $messageId = $provider->send($log);

            $log->forceFill(['provider' => $provider->name()])->save();
            $log->markSent($messageId, $provider->name());
        } catch (\Throwable $e) {
            $log->markFailed($e->getMessage());
        }

        return $log;
    }

    /**
     * Resolve and render a template.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: string, 1: string} [title, body]
     */
    public function render(string $key, string $channel, array $data = [], string $locale = 'ar'): array
    {
        $fallback = self::fallbackTemplate($key, $channel);
        $template = $this->findTemplate($key, $channel, $locale);

        if ($template !== null) {
            return [
                // A template may omit the title; fall back so clients always
                // have something to show in the notification header.
                $this->interpolate((string) ($template->title ?: $fallback['title']), $data),
                $this->interpolate($template->body, $data),
            ];
        }

        return [
            $this->interpolate($fallback['title'], $data),
            $this->interpolate($fallback['body'], $data),
        ];
    }

    /**
     * The tenant's own template wins over the platform default.
     */
    public function findTemplate(string $key, string $channel, string $locale = 'ar'): ?NotificationTemplate
    {
        return NotificationTemplate::query()
            ->where('key', $key)
            ->where('channel', $channel)
            ->where('locale', $locale)
            ->where('is_active', true)
            ->orderByRaw('tenant_id is null')  // tenant rows first, then global
            ->first();
    }

    /**
     * Replace :placeholder tokens with their values.
     *
     * @param  array<string, mixed>  $data
     */
    public function interpolate(string $text, array $data): string
    {
        foreach ($data as $name => $value) {
            $text = str_replace(':'.$name, (string) $value, $text);
        }

        return $text;
    }

    /**
     * Channels that can actually deliver right now.
     *
     * @return list<string>
     */
    public function defaultChannels(): array
    {
        return $this->channels->available();
    }

    public function userAllows(User $user, string $channel): bool
    {
        $preference = NotificationPreference::query()
            ->where('user_id', $user->getKey())
            ->where('channel', $channel)
            ->first();

        // Absence of a preference means the channel is allowed by default.
        return $preference === null || $preference->is_enabled;
    }

    protected function recipientFor(User $user, string $channel): ?string
    {
        return match ($channel) {
            NotificationTemplate::CHANNEL_IN_APP => (string) $user->getKey(),
            default => filled($user->phone) ? (string) $user->phone : null,
        };
    }

    /**
     * Built-in wording used before any template row exists.
     *
     * @return array{title: string, body: string}
     */
    public static function fallbackTemplate(string $key, string $channel): array
    {
        $catalogue = [
            'attendance.absence_alert' => [
                'title' => 'تنبيه غياب',
                'body' => 'عزيزي ولي الأمر، نلفت انتباهكم إلى غياب الطالب :student_name عن المدرسة (:absences) مرات.',
            ],
            'exams.result_published' => [
                'title' => 'صدور النتائج',
                'body' => 'تم نشر نتائج الطالب :student_name. المعدل العام: :average.',
            ],
            'fees.invoice_due' => [
                'title' => 'فاتورة مستحقة',
                'body' => 'على الطالب :student_name مبلغ مستحق بقيمة :amount.',
            ],
            'admissions.accepted' => [
                'title' => 'قبول طلب',
                'body' => 'تم قبول طلب التسجيل للطالب :student_name.',
            ],
            'auth.welcome' => [
                'title' => 'مرحباً بكم',
                'body' => 'مرحباً :name في منصة المدرسة الرقمية.',
            ],
        ];

        $template = $catalogue[$key] ?? [
            'title' => 'إشعار',
            'body' => 'لديك إشعار جديد: '.$key,
        ];

        return ['title' => $template['title'], 'body' => $template['body']];
    }
}

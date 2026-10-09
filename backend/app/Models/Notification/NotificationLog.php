<?php

namespace App\Models\Notification;

use App\Models\User;
use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One outbound message attempt (سجل الإشعارات).
 */
class NotificationLog extends Model
{
    use BelongsToTenant, HasFactory;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $attributes = [
        'status' => self::STATUS_QUEUED,
        'attempts' => 0,
    ];

    protected $fillable = [
        'tenant_id',
        'user_id',
        'channel',
        'recipient',
        'template_key',
        'subject',
        'body',
        'status',
        'attempts',
        'provider',
        'provider_message_id',
        'error',
        'meta',
        'queued_at',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'meta' => 'array',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markSent(?string $providerMessageId = null, ?string $provider = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_SENT,
            'provider_message_id' => $providerMessageId,
            'provider' => $provider ?? $this->provider,
            'sent_at' => now(),
            'error' => null,
        ])->save();
    }

    public function markFailed(string $error): void
    {
        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'error' => $error,
        ])->save();
    }
}

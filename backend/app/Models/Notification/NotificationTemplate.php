<?php

namespace App\Models\Notification;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A reusable message body for a notification key on a given channel.
 *
 * Templates are tenant-scoped but platform-level defaults (tenant_id NULL) stay
 * visible to every school, so national wording can be shipped once.
 */
class NotificationTemplate extends Model
{
    use BelongsToTenant, HasFactory;

    public const CHANNEL_SMS = 'sms';

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const CHANNEL_IN_APP = 'in_app';

    public const CHANNELS = [
        self::CHANNEL_SMS,
        self::CHANNEL_WHATSAPP,
        self::CHANNEL_IN_APP,
    ];

    protected $fillable = [
        'tenant_id',
        'key',
        'channel',
        'locale',
        'title',
        'body',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public static function includesGlobalRows(): bool
    {
        return true;
    }
}

<?php

namespace App\Models\Notification;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's opt-in/opt-out for a delivery channel.
 *
 * Preferences are intentionally NOT tenant-scoped: they belong to the account,
 * not to a school, and a user may move between schools.
 */
class NotificationPreference extends Model
{
    use HasFactory;

    protected $attributes = [
        'is_enabled' => true,
    ];

    protected $fillable = [
        'user_id',
        'channel',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

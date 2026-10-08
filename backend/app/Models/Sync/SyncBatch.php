<?php

namespace App\Models\Sync;

use App\Models\User;
use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A replayed batch of offline mutations from one device.
 */
class SyncBatch extends Model
{
    use BelongsToTenant, HasFactory;

    public const STATUS_APPLIED = 'applied';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_FAILED = 'failed';

    protected $attributes = [
        'status' => self::STATUS_APPLIED,
    ];

    protected $fillable = [
        'tenant_id',
        'user_id',
        'client_batch_id',
        'device_id',
        'item_count',
        'applied_count',
        'failed_count',
        'status',
        'results',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'item_count' => 'integer',
            'applied_count' => 'integer',
            'failed_count' => 'integer',
            'results' => 'array',
            'received_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

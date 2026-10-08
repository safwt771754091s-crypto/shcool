<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AchievementAward extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'achievement_id',
        'awardable_type',
        'awardable_id',
        'awarded_by',
        'reason',
        'awarded_at',
    ];

    protected function casts(): array
    {
        return [
            'awarded_at' => 'datetime',
        ];
    }

    public function achievement(): BelongsTo
    {
        return $this->belongsTo(Achievement::class);
    }

    public function awardable(): MorphTo
    {
        return $this->morphTo();
    }

    public function awardedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }
}

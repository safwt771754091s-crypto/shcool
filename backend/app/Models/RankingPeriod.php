<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RankingPeriod extends Model
{
    use HasFactory;

    public const TYPE_TERM = 'term';

    public const TYPE_SEMESTER = 'semester';

    public const TYPE_MONTH = 'month';

    public const TYPE_YEAR = 'year';

    public const TYPE_CUSTOM = 'custom';

    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'starts_on',
        'ends_on',
        'is_active',
        'is_locked',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
            'is_locked' => 'boolean',
        ];
    }

    public function scores(): HasMany
    {
        return $this->hasMany(CompetitionScore::class);
    }

    public function leaderboardEntries(): HasMany
    {
        return $this->hasMany(LeaderboardEntry::class);
    }

    public function containsDate(\DateTimeInterface $date): bool
    {
        return $date >= $this->starts_on && $date <= $this->ends_on;
    }
}

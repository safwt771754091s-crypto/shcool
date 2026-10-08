<?php

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CompetitionScore extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'ranking_period_id',
        'competition_metric_id',
        'scorable_type',
        'scorable_id',
        'raw_value',
        'points',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'raw_value' => 'decimal:4',
            'points' => 'decimal:4',
            'meta' => 'array',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(RankingPeriod::class, 'ranking_period_id');
    }

    public function metric(): BelongsTo
    {
        return $this->belongsTo(CompetitionMetric::class, 'competition_metric_id');
    }

    public function scorable(): MorphTo
    {
        return $this->morphTo();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A ranked row for one entity (student, class, school, directorate,
 * governorate, ministry) in one ranking period.
 */
class LeaderboardEntry extends Model
{
    use HasFactory;

    public const SCOPE_STUDENT = 'student';

    public const SCOPE_CLASS = 'class';

    public const SCOPE_SCHOOL = 'school';

    public const SCOPE_DIRECTORATE = 'directorate';

    public const SCOPE_GOVERNORATE = 'governorate';

    public const SCOPE_MINISTRY = 'ministry';

    /**
     * Ordered from the smallest to the largest competitive scope.
     *
     * @var list<string>
     */
    public const SCOPES = [
        self::SCOPE_STUDENT,
        self::SCOPE_CLASS,
        self::SCOPE_SCHOOL,
        self::SCOPE_DIRECTORATE,
        self::SCOPE_GOVERNORATE,
        self::SCOPE_MINISTRY,
    ];

    protected $fillable = [
        'ranking_period_id',
        'scope_type',
        'scope_id',
        'tenant_id',
        'name',
        'total_points',
        'rank',
        'previous_rank',
        'rank_delta',
        'sample_size',
        'breakdown',
        'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'total_points' => 'decimal:4',
            'breakdown' => 'array',
            'computed_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(RankingPeriod::class, 'ranking_period_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'tenant_id');
    }
}

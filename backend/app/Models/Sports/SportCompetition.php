<?php

namespace App\Models\Sports;

use App\Models\User;
use App\Support\Competition\CompetitionScope;
use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SportCompetition extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_GROUP_STAGE = 'group_stage';

    public const STATUS_KNOCKOUT = 'knockout';

    public const STATUS_FINISHED = 'finished';

    public const STATUS_CANCELLED = 'cancelled';

    protected $attributes = [
        'current_scope' => CompetitionScope::SECTION,
        'status' => self::STATUS_DRAFT,
    ];

    protected $fillable = [
        'tenant_id',
        'name',
        'sport',
        'stage',
        'age_group',
        'current_scope',
        'status',
        'starts_on',
        'ends_on',
        'organizer_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }


    /**
     * Platform-wide rows (tenant_id IS NULL) stay visible to every tenant.
     */
    public static function includesGlobalRows(): bool
    {
        return true;
    }
    public function participants(): HasMany
    {
        return $this->hasMany(SportParticipant::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(SportMatch::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function matchesAtScope(string $scope): HasMany
    {
        return $this->matches()->where('scope_type', $scope);
    }

    public function scopeLadderPosition(): int
    {
        return CompetitionScope::rank($this->current_scope);
    }

    /**
     * Move the tournament to the next rung of the ladder.
     */
    public function advanceScope(): bool
    {
        $next = CompetitionScope::next($this->current_scope);

        if ($next === null) {
            $this->forceFill(['status' => self::STATUS_FINISHED])->save();

            return false;
        }

        $this->forceFill([
            'current_scope' => $next,
            'status' => self::STATUS_KNOCKOUT,
        ])->save();

        return true;
    }
}

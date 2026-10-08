<?php

namespace App\Models\Sports;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SportParticipant extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'sport_competition_id',
        'scope_type',
        'scope_id',
        'name',
        'coach',
        'seed',
    ];

    protected function casts(): array
    {
        return ['seed' => 'integer'];
    }


    /**
     * Platform-wide rows (tenant_id IS NULL) stay visible to every tenant.
     */
    public static function includesGlobalRows(): bool
    {
        return true;
    }
    public function competition(): BelongsTo
    {
        return $this->belongsTo(SportCompetition::class, 'sport_competition_id');
    }

    public function homeMatches(): HasMany
    {
        return $this->hasMany(SportMatch::class, 'home_participant_id');
    }

    public function awayMatches(): HasMany
    {
        return $this->hasMany(SportMatch::class, 'away_participant_id');
    }
}

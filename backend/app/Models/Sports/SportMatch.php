<?php

namespace App\Models\Sports;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SportMatch extends Model
{
    use BelongsToTenant, HasFactory;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_LIVE = 'live';

    public const STATUS_PLAYED = 'played';

    public const STATUS_WALKOVER = 'walkover';

    public const STATUS_CANCELLED = 'cancelled';

    protected $attributes = [
        'status' => self::STATUS_SCHEDULED,
        'round_number' => 1,
    ];

    protected $fillable = [
        'tenant_id',
        'sport_competition_id',
        'scope_type',
        'round_number',
        'bracket_slot',
        'home_participant_id',
        'away_participant_id',
        'home_score',
        'away_score',
        'winner_participant_id',
        'next_match_id',
        'status',
        'played_at',
        'venue',
    ];

    protected function casts(): array
    {
        return [
            'round_number' => 'integer',
            'bracket_slot' => 'integer',
            'home_score' => 'integer',
            'away_score' => 'integer',
            'played_at' => 'datetime',
        ];
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

    public function home(): BelongsTo
    {
        return $this->belongsTo(SportParticipant::class, 'home_participant_id');
    }

    public function away(): BelongsTo
    {
        return $this->belongsTo(SportParticipant::class, 'away_participant_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(SportParticipant::class, 'winner_participant_id');
    }

    public function nextMatch(): BelongsTo
    {
        return $this->belongsTo(self::class, 'next_match_id');
    }

    public function isPlayed(): bool
    {
        return in_array($this->status, [self::STATUS_PLAYED, self::STATUS_WALKOVER], true);
    }

    /**
     * The participant that wins on the given scores, or null for a draw.
     */
    public function decideWinner(): ?SportParticipant
    {
        if ($this->home_score === null || $this->away_score === null) {
            return null;
        }

        if ($this->home_score === $this->away_score) {
            return null;
        }

        return $this->home_score > $this->away_score
            ? $this->home()->first()
            : $this->away()->first();
    }
}

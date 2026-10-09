<?php

namespace App\Services\Competition;

use App\Models\Achievement;
use App\Models\AchievementAward;
use App\Models\LeaderboardEntry;
use App\Models\RankingPeriod;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Facade over the competition subsystem used by controllers.
 */
class CompetitionService
{
    public function __construct(
        protected LeaderboardService $leaderboards,
        protected ScoreCalculator $calculator,
        protected AuditLogger $audit,
    ) {}

    /**
     * The ranked table for a scope in a period.
     *
     * @return Collection<int, LeaderboardEntry>
     */
    public function leaderboard(RankingPeriod $period, string $scope, ?int $limit = null): Collection
    {
        return LeaderboardEntry::query()
            ->where('ranking_period_id', $period->getKey())
            ->where('scope_type', $scope)
            ->orderBy('rank')
            ->when($limit, fn ($q) => $q->limit($limit))
            ->get();
    }

    /**
     * Award a badge to an entity and log it.
     */
    public function award(Achievement $achievement, Model $awardable, ?int $awardedBy = null, ?string $reason = null): AchievementAward
    {
        $award = AchievementAward::create([
            'tenant_id' => $awardable->getAttribute('tenant_id'),
            'achievement_id' => $achievement->getKey(),
            'awardable_type' => $awardable->getMorphClass(),
            'awardable_id' => $awardable->getKey(),
            'awarded_by' => $awardedBy,
            'reason' => $reason,
            'awarded_at' => now(),
        ]);

        $this->audit->log('achievement.awarded', $award, description: "Awarded {$achievement->name}.");

        return $award;
    }

    public function recompute(RankingPeriod $period): void
    {
        $this->leaderboards->recomputeAll($period);
    }

    /**
     * Full recompute: measure students from real academic data, then roll the
     * results up the administrative hierarchy.
     */
    public function recomputeFromAcademics(RankingPeriod $period): void
    {
        $this->leaderboards->recomputeFromAcademics($period);
    }
}

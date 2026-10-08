<?php

namespace App\Services\Competition;

use App\Models\LeaderboardEntry;
use App\Models\Organization;
use App\Models\RankingPeriod;
use App\Support\Tenancy\TenantManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds and persists rankings for every competitive scope:
 *
 *   student -> class -> school -> directorate -> governorate -> ministry
 *
 * Score rows are produced per student by ScoreCalculator; this service rolls
 * them up the administrative hierarchy and materialises ranked
 * LeaderboardEntry rows, so the ministry dashboard is a plain indexed read.
 */
class LeaderboardService
{
    public function __construct(protected TenantManager $tenants)
    {
    }

    /**
     * Recompute every scope for a period, from the smallest scope upward.
     */
    public function recomputeAll(RankingPeriod $period): void
    {
        $this->tenants->withoutTenancy(function () use ($period): void {
            $this->recomputeClasses($period);
            $this->recomputeSchools($period);
            $this->recomputeDirectorates($period);
            $this->recomputeGovernorates($period);
            $this->recomputeMinistry($period);
        });
    }

    /**
     * Aggregate a child scope into its parent scope and rank the result.
     *
     * @param  string  $parentScope  LeaderboardEntry scope constant to write.
     * @param  string  $childScope   LeaderboardEntry scope constant to read.
     * @param  callable(LeaderboardEntry): (int|null)  $parentOf
     */
    public function rollUp(
        RankingPeriod $period,
        string $parentScope,
        string $childScope,
        callable $parentOf,
    ): void {
        $children = LeaderboardEntry::query()
            ->where('ranking_period_id', $period->getKey())
            ->where('scope_type', $childScope)
            ->get();

        $grouped = $children->groupBy(fn (LeaderboardEntry $entry) => (int) $parentOf($entry));

        $names = $this->organizationNames($grouped->keys()->filter()->all());

        $rows = $grouped
            ->reject(fn (Collection $group, $parentId) => (int) $parentId === 0)
            ->map(fn (Collection $group, $parentId) => [
                'scope_id' => (int) $parentId,
                'name' => $names[(int) $parentId] ?? ('#'.$parentId),
                // Averaging keeps schools/directorates of different sizes
                // comparable; sample_size records how many entities contributed.
                'total_points' => round((float) $group->avg('total_points'), 4),
                'sample_size' => $group->count(),
            ])
            ->values();

        $this->persist($period, $parentScope, $rows);
    }

    /**
     * Materialise ranked rows for one scope. Ranks are assigned by total_points
     * descending; the previous rank is preserved to compute the movement delta.
     *
     * @param  Collection<int, array{scope_id: int, name: string, total_points: float, sample_size: int}>  $rows
     */
    public function persist(RankingPeriod $period, string $scope, Collection $rows): void
    {
        $ranked = $rows->sortByDesc('total_points')->values();

        DB::transaction(function () use ($period, $scope, $ranked): void {
            $previous = LeaderboardEntry::query()
                ->where('ranking_period_id', $period->getKey())
                ->where('scope_type', $scope)
                ->pluck('rank', 'scope_id');

            foreach ($ranked as $index => $row) {
                $rank = $index + 1;
                $previousRank = $previous[$row['scope_id']] ?? null;

                LeaderboardEntry::query()->updateOrCreate(
                    [
                        'ranking_period_id' => $period->getKey(),
                        'scope_type' => $scope,
                        'scope_id' => $row['scope_id'],
                    ],
                    [
                        'tenant_id' => $scope === LeaderboardEntry::SCOPE_SCHOOL
                            ? $row['scope_id']
                            : null,
                        'name' => $row['name'],
                        'total_points' => $row['total_points'],
                        'sample_size' => $row['sample_size'],
                        'rank' => $rank,
                        'previous_rank' => $previousRank,
                        'rank_delta' => $previousRank ? ($previousRank - $rank) : 0,
                        'computed_at' => now(),
                    ],
                );
            }
        });
    }

    protected function recomputeClasses(RankingPeriod $period): void
    {
        // Populated by the academic module once classes exist. Kept explicit so
        // recomputeAll() documents the full pipeline.
    }

    protected function recomputeSchools(RankingPeriod $period): void
    {
        $this->rollUp(
            $period,
            LeaderboardEntry::SCOPE_SCHOOL,
            LeaderboardEntry::SCOPE_CLASS,
            fn (LeaderboardEntry $entry) => $this->schoolIdOf($entry->tenant_id),
        );
    }

    protected function recomputeDirectorates(RankingPeriod $period): void
    {
        $this->rollUp(
            $period,
            LeaderboardEntry::SCOPE_DIRECTORATE,
            LeaderboardEntry::SCOPE_SCHOOL,
            fn (LeaderboardEntry $entry) => $this->ancestorOfType($entry->scope_id, Organization::TYPE_DIRECTORATE),
        );
    }

    protected function recomputeGovernorates(RankingPeriod $period): void
    {
        $this->rollUp(
            $period,
            LeaderboardEntry::SCOPE_GOVERNORATE,
            LeaderboardEntry::SCOPE_DIRECTORATE,
            fn (LeaderboardEntry $entry) => $this->ancestorOfType($entry->scope_id, Organization::TYPE_GOVERNORATE),
        );
    }

    protected function recomputeMinistry(RankingPeriod $period): void
    {
        $this->rollUp(
            $period,
            LeaderboardEntry::SCOPE_MINISTRY,
            LeaderboardEntry::SCOPE_GOVERNORATE,
            fn () => $this->ministryId(),
        );
    }

    /**
     * The school a class belongs to is the class's tenant.
     */
    protected function schoolIdOf(?int $tenantId): ?int
    {
        return $tenantId;
    }

    /**
     * Walk up the materialized path until an organization of $type is found.
     */
    protected function ancestorOfType(?int $organizationId, string $type): ?int
    {
        if (! $organizationId) {
            return null;
        }

        $node = Organization::query()->find($organizationId);

        if (! $node) {
            return null;
        }

        if ($node->type === $type) {
            return $node->getKey();
        }

        return $node->ancestors()->firstWhere('type', $type)?->getKey();
    }

    protected function ministryId(): ?int
    {
        return Organization::query()->where('type', Organization::TYPE_MINISTRY)->value('id');
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    protected function organizationNames(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Organization::query()->whereIn('id', $ids)->pluck('name', 'id')->all();
    }
}

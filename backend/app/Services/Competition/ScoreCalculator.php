<?php

namespace App\Services\Competition;

use App\Models\CompetitionMetric;
use App\Models\CompetitionScore;
use App\Support\Tenancy\Scopes\TenantScope;

/**
 * Converts a raw measured value into weighted points.
 *
 * The weighting is deliberately simple and explainable:
 *   points = normalized(raw) * weight
 * where normalized() maps the raw value to the 0..100 range. Callers supply the
 * observed min/max for the cohort so the normalisation is fair within a period.
 */
class ScoreCalculator
{
    public function pointsFor(
        CompetitionMetric $metric,
        float $rawValue,
        float $min = 0,
        float $max = 100,
    ): float {
        $normalized = $this->normalize($rawValue, $min, $max, $metric->direction);

        return round($normalized * (float) $metric->weight, 4);
    }

    /**
     * Record (or update) a score row for one entity in one period.
     *
     * @param  array<string, mixed>  $meta
     */
    public function record(
        int $tenantId,
        int $periodId,
        CompetitionMetric $metric,
        string $scorableType,
        int $scorableId,
        float $rawValue,
        float $min = 0,
        float $max = 100,
        array $meta = [],
    ): CompetitionScore {
        return CompetitionScore::withoutGlobalScope(TenantScope::class)
            ->updateOrCreate(
                [
                    'ranking_period_id' => $periodId,
                    'competition_metric_id' => $metric->getKey(),
                    'scorable_type' => $scorableType,
                    'scorable_id' => $scorableId,
                ],
                [
                    'tenant_id' => $tenantId,
                    'raw_value' => $rawValue,
                    'points' => $this->pointsFor($metric, $rawValue, $min, $max),
                    'meta' => $meta ?: null,
                ],
            );
    }

    /**
     * Map a raw value to 0..100. "lower" metrics are inverted so that a smaller
     * raw value yields more points.
     */
    protected function normalize(float $value, float $min, float $max, string $direction): float
    {
        if ($max <= $min) {
            return 0.0;
        }

        $ratio = ($value - $min) / ($max - $min);
        $ratio = max(0.0, min(1.0, $ratio));

        if ($direction === CompetitionMetric::DIRECTION_LOWER) {
            $ratio = 1.0 - $ratio;
        }

        return $ratio * 100.0;
    }
}

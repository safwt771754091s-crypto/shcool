<?php

namespace Tests\Feature;

use App\Models\CompetitionMetric;
use App\Models\CompetitionScore;
use App\Models\Organization;
use App\Models\RankingPeriod;
use App\Support\Tenancy\Exceptions\TenantNotResolvedException;
use App\Support\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenancyIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Strict mode turns "no tenant" into a loud error instead of an empty set.
        config()->set('tenancy.strict', true);
    }

    public function test_rows_are_isolated_between_tenants(): void
    {
        $schoolA = Organization::factory()->tenant()->create();
        $schoolB = Organization::factory()->tenant()->create();

        $period = RankingPeriod::create([
            'name' => 'الفصل الأول',
            'type' => RankingPeriod::TYPE_TERM,
            'starts_on' => now()->startOfYear(),
            'ends_on' => now()->endOfYear(),
        ]);

        $metric = CompetitionMetric::create([
            'key' => 'academic.average',
            'name' => 'المعدل',
            'direction' => CompetitionMetric::DIRECTION_HIGHER,
            'weight' => 5,
        ]);

        $this->actingAsTenant($schoolA);
        CompetitionScore::create([
            'ranking_period_id' => $period->id,
            'competition_metric_id' => $metric->id,
            'scorable_type' => 'student',
            'scorable_id' => 1,
            'raw_value' => 90,
            'points' => 450,
        ]);

        $this->actingAsTenant($schoolB);
        CompetitionScore::create([
            'ranking_period_id' => $period->id,
            'competition_metric_id' => $metric->id,
            'scorable_type' => 'student',
            'scorable_id' => 2,
            'raw_value' => 80,
            'points' => 400,
        ]);

        // Tenant B sees only its own row.
        $this->assertSame(1, CompetitionScore::query()->count());
        $this->assertSame(2, CompetitionScore::query()->first()->scorable_id);

        // Tenant A sees only its own row.
        $this->actingAsTenant($schoolA);
        $this->assertSame(1, CompetitionScore::query()->count());
        $this->assertSame(1, CompetitionScore::query()->first()->scorable_id);
    }

    public function test_tenant_id_is_stamped_automatically_on_create(): void
    {
        $school = Organization::factory()->tenant()->create();
        $this->actingAsTenant($school);

        $period = RankingPeriod::create([
            'name' => 'الفصل الأول',
            'type' => RankingPeriod::TYPE_TERM,
            'starts_on' => now()->startOfYear(),
            'ends_on' => now()->endOfYear(),
        ]);

        $metric = CompetitionMetric::create([
            'key' => 'attendance.rate',
            'name' => 'الحضور',
            'direction' => CompetitionMetric::DIRECTION_HIGHER,
            'weight' => 1,
        ]);

        $score = CompetitionScore::create([
            'ranking_period_id' => $period->id,
            'competition_metric_id' => $metric->id,
            'scorable_type' => 'student',
            'scorable_id' => 5,
            'raw_value' => 100,
            'points' => 100,
        ]);

        $this->assertSame($school->id, $score->tenant_id);
    }

    public function test_querying_without_a_tenant_throws_in_strict_mode(): void
    {
        $this->expectException(TenantNotResolvedException::class);

        CompetitionScore::query()->count();
    }

    public function test_without_tenancy_bypasses_isolation(): void
    {
        $schoolA = Organization::factory()->tenant()->create();
        $schoolB = Organization::factory()->tenant()->create();

        $period = RankingPeriod::create([
            'name' => 'P',
            'type' => RankingPeriod::TYPE_TERM,
            'starts_on' => now(),
            'ends_on' => now()->addMonth(),
        ]);
        $metric = CompetitionMetric::create([
            'key' => 'k', 'name' => 'K',
            'direction' => CompetitionMetric::DIRECTION_HIGHER, 'weight' => 1,
        ]);

        foreach ([$schoolA, $schoolB] as $school) {
            $this->actingAsTenant($school);
            CompetitionScore::create([
                'ranking_period_id' => $period->id,
                'competition_metric_id' => $metric->id,
                'scorable_type' => 'student',
                'scorable_id' => $school->id,
                'raw_value' => 1,
                'points' => 1,
            ]);
        }

        $total = app(TenantManager::class)->withoutTenancy(
            fn () => CompetitionScore::query()->count()
        );

        $this->assertSame(2, $total);
    }
}

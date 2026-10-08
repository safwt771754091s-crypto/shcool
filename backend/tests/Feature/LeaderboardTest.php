<?php

namespace Tests\Feature;

use App\Models\LeaderboardEntry;
use App\Models\Organization;
use App\Models\RankingPeriod;
use App\Services\Competition\LeaderboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaderboardTest extends TestCase
{
    use RefreshDatabase;

    protected function period(): RankingPeriod
    {
        return RankingPeriod::create([
            'name' => 'الفصل الأول',
            'type' => RankingPeriod::TYPE_TERM,
            'starts_on' => now()->startOfYear(),
            'ends_on' => now()->endOfYear(),
        ]);
    }

    /**
     * Build the hierarchy: ministry -> governorate -> 2 directorates -> schools.
     *
     * @return array{ministry: Organization, governorate: Organization, dirA: Organization, dirB: Organization}
     */
    protected function hierarchy(): array
    {
        $ministry = Organization::factory()->ministry()->create();
        $governorate = Organization::factory()->governorate()->childOf($ministry)->create();
        $dirA = Organization::factory()->directorate()->childOf($governorate)->create();
        $dirB = Organization::factory()->directorate()->childOf($governorate)->create();

        return compact('ministry', 'governorate', 'dirA', 'dirB');
    }

    public function test_schools_are_ranked_by_points(): void
    {
        $h = $this->hierarchy();
        $period = $this->period();

        $schoolA = Organization::factory()->tenant()->childOf($h['dirA'])->create();
        $schoolB = Organization::factory()->tenant()->childOf($h['dirA'])->create();

        // Two class entries per school feed the roll-up.
        $this->classEntry($period, $schoolA, 900);
        $this->classEntry($period, $schoolB, 700);

        app(LeaderboardService::class)->recomputeAll($period);

        $first = LeaderboardEntry::query()
            ->where('scope_type', LeaderboardEntry::SCOPE_SCHOOL)
            ->orderBy('rank')
            ->get();

        $this->assertCount(2, $first);
        $this->assertSame($schoolA->id, (int) $first[0]->scope_id);
        $this->assertSame(1, $first[0]->rank);
        $this->assertSame($schoolB->id, (int) $first[1]->scope_id);
        $this->assertSame(2, $first[1]->rank);
    }

    public function test_rollup_reaches_directorate_governorate_and_ministry(): void
    {
        $h = $this->hierarchy();
        $period = $this->period();

        $schoolA = Organization::factory()->tenant()->childOf($h['dirA'])->create();
        $schoolB = Organization::factory()->tenant()->childOf($h['dirB'])->create();

        $this->classEntry($period, $schoolA, 900);
        $this->classEntry($period, $schoolB, 600);

        app(LeaderboardService::class)->recomputeAll($period);

        $directorates = LeaderboardEntry::query()
            ->where('scope_type', LeaderboardEntry::SCOPE_DIRECTORATE)->get();
        $this->assertCount(2, $directorates);
        $this->assertSame($h['dirA']->id, (int) $directorates->firstWhere('rank', 1)->scope_id);

        $governorates = LeaderboardEntry::query()
            ->where('scope_type', LeaderboardEntry::SCOPE_GOVERNORATE)->get();
        $this->assertCount(1, $governorates);
        $this->assertSame($h['governorate']->id, (int) $governorates->first()->scope_id);

        $ministry = LeaderboardEntry::query()
            ->where('scope_type', LeaderboardEntry::SCOPE_MINISTRY)->get();
        $this->assertCount(1, $ministry);
        $this->assertSame($h['ministry']->id, (int) $ministry->first()->scope_id);
    }

    public function test_rank_delta_is_recorded_when_positions_change(): void
    {
        $h = $this->hierarchy();
        $period = $this->period();

        $schoolA = Organization::factory()->tenant()->childOf($h['dirA'])->create();
        $schoolB = Organization::factory()->tenant()->childOf($h['dirA'])->create();

        $this->classEntry($period, $schoolA, 900);
        $this->classEntry($period, $schoolB, 700);
        app(LeaderboardService::class)->recomputeAll($period);

        // Flip the scores and recompute.
        LeaderboardEntry::query()->where('scope_type', LeaderboardEntry::SCOPE_CLASS)->delete();
        $this->classEntry($period, $schoolA, 500);
        $this->classEntry($period, $schoolB, 950);
        app(LeaderboardService::class)->recomputeAll($period);

        $schoolBEntry = LeaderboardEntry::query()
            ->where('scope_type', LeaderboardEntry::SCOPE_SCHOOL)
            ->where('scope_id', $schoolB->id)
            ->firstOrFail();

        $this->assertSame(1, $schoolBEntry->rank);
        $this->assertSame(2, $schoolBEntry->previous_rank);
        $this->assertSame(1, $schoolBEntry->rank_delta);
    }

    protected function classEntry(RankingPeriod $period, Organization $school, float $points): LeaderboardEntry
    {
        return LeaderboardEntry::create([
            'ranking_period_id' => $period->id,
            'scope_type' => LeaderboardEntry::SCOPE_CLASS,
            'scope_id' => random_int(1000, 9999),
            'tenant_id' => $school->id,
            'name' => 'شعبة',
            'total_points' => $points,
            'sample_size' => 1,
            'computed_at' => now(),
        ]);
    }
}

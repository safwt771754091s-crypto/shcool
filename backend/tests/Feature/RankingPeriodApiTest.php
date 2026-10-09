<?php

namespace Tests\Feature;

use App\Models\Academic\SchoolClass;
use App\Models\Academic\Subject;
use App\Models\Exam\Exam;
use App\Models\LeaderboardEntry;
use App\Models\Organization;
use App\Models\RankingPeriod;
use App\Models\Student\Student;
use App\Models\User;
use App\Services\Competition\LeaderboardService;
use App\Services\Exams\GradeService;
use App\Support\Permission\RoleProvisioner;
use App\Support\Permission\Roles;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RankingPeriodApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    /**
     * @return array{school: Organization, user: User, token: string, period: RankingPeriod}
     */
    protected function context(): array
    {
        $school = Organization::factory()->tenant()->create();
        $this->actingAsTenant($school);
        app(RoleProvisioner::class)->provisionTenantRoles($school);

        $user = User::factory()->forTenant($school->id)->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);
        $user->assignRole(Roles::SCHOOL_MANAGER);

        $class = SchoolClass::create(['name' => 'الأول', 'grade' => 1]);
        $section = $class->sections()->create(['name' => 'أ']);
        $subject = Subject::create([
            'name' => 'الرياضيات',
            'code' => 'MATH',
            'pass_mark' => 50,
            'max_mark' => 100,
        ]);

        $students = collect(range(1, 2))->map(fn (int $i) => Student::create([
            'class_section_id' => $section->getKey(),
            'student_number' => 'S-000'.$i,
            'full_name' => 'طالب '.$i,
            'status' => Student::STATUS_ENROLLED,
        ]));

        $exam = Exam::create([
            'subject_id' => $subject->getKey(),
            'class_section_id' => $section->getKey(),
            'title' => 'اختبار',
            'max_mark' => 100,
            'pass_mark' => 50,
        ]);

        app(GradeService::class)->recordMany($exam, [
            ['student_id' => $students[0]->id, 'mark' => 95],
            ['student_id' => $students[1]->id, 'mark' => 60],
        ]);

        $period = RankingPeriod::create([
            'name' => 'الفصل الأول',
            'type' => RankingPeriod::TYPE_TERM,
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-12-31',
            'is_active' => true,
        ]);

        return [
            'school' => $school,
            'user' => $user,
            'token' => $user->createToken('test')->plainTextToken,
            'period' => $period,
        ];
    }

    public function test_periods_endpoint_lists_active_periods(): void
    {
        ['token' => $token, 'period' => $period] = $this->context();

        $response = $this->withToken($token)
            ->getJson('/api/v1/ranking-periods?active_only=1');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $period->id)
            ->assertJsonPath('data.0.name', 'الفصل الأول')
            ->assertJsonPath('data.0.is_active', true);
    }

    public function test_leaderboard_endpoint_returns_ranked_schools(): void
    {
        ['token' => $token, 'period' => $period] = $this->context();

        app(LeaderboardService::class)->recomputeFromAcademics($period);

        $response = $this->withToken($token)
            ->getJson("/api/v1/ranking-periods/{$period->id}/leaderboard?scope=school");

        $response->assertOk()
            ->assertJsonPath('meta.scope', 'school')
            ->assertJsonPath('data.0.scope_type', LeaderboardEntry::SCOPE_SCHOOL);

        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_store_period_requires_validation(): void
    {
        ['token' => $token] = $this->context();

        $this->withToken($token)
            ->postJson('/api/v1/ranking-periods', ['name' => 'ناقص'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['starts_on', 'ends_on']);

        $this->withToken($token)
            ->postJson('/api/v1/ranking-periods', [
                'name' => 'الفصل الثاني',
                'type' => RankingPeriod::TYPE_SEMESTER,
                'starts_on' => '2027-01-01',
                'ends_on' => '2027-06-30',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'الفصل الثاني');

        $this->assertDatabaseHas('ranking_periods', ['name' => 'الفصل الثاني']);
    }
}

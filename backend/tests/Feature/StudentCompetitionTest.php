<?php

namespace Tests\Feature;

use App\Models\Academic\SchoolClass;
use App\Models\Academic\Subject;
use App\Models\Attendance\Attendance;
use App\Models\CompetitionMetric;
use App\Models\CompetitionScore;
use App\Models\Exam\Exam;
use App\Models\LeaderboardEntry;
use App\Models\Organization;
use App\Models\RankingPeriod;
use App\Models\Student\Student;
use App\Services\Attendance\AttendanceService;
use App\Services\Competition\LeaderboardService;
use App\Services\Exams\GradeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentCompetitionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            ['academic.average', 'المعدل', CompetitionMetric::DIRECTION_HIGHER, 5],
            ['attendance.rate', 'الحضور', CompetitionMetric::DIRECTION_HIGHER, 3],
        ] as [$key, $name, $direction, $weight]) {
            CompetitionMetric::create([
                'key' => $key,
                'name' => $name,
                'direction' => $direction,
                'weight' => $weight,
                'is_active' => true,
            ]);
        }
    }

    protected function period(): RankingPeriod
    {
        return RankingPeriod::create([
            'name' => 'الفصل الأول',
            'type' => RankingPeriod::TYPE_TERM,
            'starts_on' => '2026-09-01',
            'ends_on' => '2026-12-31',
        ]);
    }

    /**
     * @return array{school: Organization, section: \App\Models\Academic\ClassSection, students: \Illuminate\Support\Collection}
     */
    protected function school(): array
    {
        $school = Organization::factory()->tenant()->create();
        $this->actingAsTenant($school);

        $class = SchoolClass::create(['name' => 'الأول', 'grade' => 1]);
        $section = $class->sections()->create(['name' => 'أ']);
        $subject = Subject::create(['name' => 'الرياضيات', 'code' => 'MATH', 'pass_mark' => 50, 'max_mark' => 100]);

        $students = collect(range(1, 3))->map(fn (int $i) => Student::create([
            'class_section_id' => $section->getKey(),
            'student_number' => 'S-000'.$i,
            'full_name' => 'طالب '.$i,
            'status' => Student::STATUS_ENROLLED,
        ]));

        // Grades: student 1 best, student 3 weakest.
        $exam = Exam::create([
            'subject_id' => $subject->getKey(),
            'class_section_id' => $section->getKey(),
            'title' => 'اختبار',
            'max_mark' => 100,
            'pass_mark' => 50,
        ]);

        app(GradeService::class)->recordMany($exam, [
            ['student_id' => $students[0]->id, 'mark' => 95],
            ['student_id' => $students[1]->id, 'mark' => 70],
            ['student_id' => $students[2]->id, 'mark' => 40],
        ]);

        // Attendance: student 1 perfect, student 3 mostly absent.
        $attendance = app(AttendanceService::class);
        foreach (['2026-10-01', '2026-10-02', '2026-10-03', '2026-10-04'] as $index => $date) {
            $attendance->takeRegister($section->getKey(), $date, [
                ['student_id' => $students[0]->id, 'status' => Attendance::STATUS_PRESENT],
                ['student_id' => $students[1]->id, 'status' => $index < 2 ? Attendance::STATUS_PRESENT : Attendance::STATUS_ABSENT],
                ['student_id' => $students[2]->id, 'status' => $index === 0 ? Attendance::STATUS_PRESENT : Attendance::STATUS_ABSENT],
            ]);
        }

        return compact('school', 'section', 'students');
    }

    public function test_scores_are_measured_for_every_student(): void
    {
        $this->school();
        $period = $this->period();

        app(LeaderboardService::class)->recomputeFromAcademics($period);

        $scores = CompetitionScore::allTenants()
            ->where('ranking_period_id', $period->id)
            ->where('scorable_type', 'student')
            ->get();

        // 3 students x 2 metrics.
        $this->assertCount(6, $scores);
        $this->assertTrue($scores->every(fn (CompetitionScore $s) => $s->points !== null));
    }

    public function test_students_are_ranked_and_classes_aggregate(): void
    {
        ['school' => $school, 'section' => $section, 'students' => $students] = $this->school();
        $period = $this->period();

        app(LeaderboardService::class)->recomputeFromAcademics($period);

        $studentBoard = LeaderboardEntry::query()
            ->where('ranking_period_id', $period->id)
            ->where('scope_type', LeaderboardEntry::SCOPE_STUDENT)
            ->orderBy('rank')
            ->get();

        $this->assertCount(3, $studentBoard);
        $this->assertSame($students[0]->id, (int) $studentBoard[0]->scope_id);
        $this->assertSame('طالب 1', $studentBoard[0]->name);
        $this->assertSame(1, $studentBoard[0]->rank);
        $this->assertSame(3, $studentBoard[2]->rank);

        $classBoard = LeaderboardEntry::query()
            ->where('ranking_period_id', $period->id)
            ->where('scope_type', LeaderboardEntry::SCOPE_CLASS)
            ->get();

        $this->assertCount(1, $classBoard);
        $this->assertSame($section->id, (int) $classBoard[0]->scope_id);
        $this->assertSame(3, $classBoard[0]->sample_size);
        $this->assertIsArray($classBoard[0]->breakdown);
        $this->assertArrayHasKey('academic.average', $classBoard[0]->breakdown);
    }

    public function test_class_board_rolls_up_to_the_school(): void
    {
        ['school' => $school] = $this->school();
        $period = $this->period();

        app(LeaderboardService::class)->recomputeFromAcademics($period);

        $schoolEntry = LeaderboardEntry::query()
            ->where('ranking_period_id', $period->id)
            ->where('scope_type', LeaderboardEntry::SCOPE_SCHOOL)
            ->where('scope_id', $school->id)
            ->first();

        $this->assertNotNull($schoolEntry);
        $this->assertSame(1, $schoolEntry->rank);
    }

    public function test_schools_are_isolated_in_the_ranking(): void
    {
        ['school' => $schoolA] = $this->school();

        $schoolB = Organization::factory()->tenant()->create();
        $this->actingAsTenant($schoolB);

        $period = $this->period();
        app(LeaderboardService::class)->recomputeFromAcademics($period);

        // The recompute spans every school, but each student row is attributed
        // to the school that owns it; school B contributes none.
        $this->assertSame(3, LeaderboardEntry::query()
            ->where('ranking_period_id', $period->id)
            ->where('scope_type', LeaderboardEntry::SCOPE_STUDENT)
            ->where('tenant_id', $schoolA->id)
            ->count());

        $this->assertSame(0, LeaderboardEntry::query()
            ->where('ranking_period_id', $period->id)
            ->where('scope_type', LeaderboardEntry::SCOPE_STUDENT)
            ->where('tenant_id', $schoolB->id)
            ->count());
    }
}

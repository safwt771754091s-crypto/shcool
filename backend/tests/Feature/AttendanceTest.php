<?php

namespace Tests\Feature;

use App\Models\Academic\SchoolClass;
use App\Models\Attendance\Attendance;
use App\Models\Organization;
use App\Models\Student\Student;
use App\Services\Attendance\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function sectionWithStudents(Organization $school, int $count = 3): array
    {
        $class = SchoolClass::create([
            'name' => 'الصف الأول الابتدائي',
            'grade' => 1,
            'stage' => 'primary',
        ]);
        $section = $class->sections()->create(['name' => 'أ']);

        $students = collect(range(1, $count))->map(fn (int $i) => Student::create([
            'class_section_id' => $section->getKey(),
            'student_number' => 'S-000'.$i,
            'full_name' => 'طالب '.$i,
            'status' => Student::STATUS_ENROLLED,
        ]));

        return [$section, $students];
    }

    public function test_register_upserts_one_row_per_student(): void
    {
        $school = Organization::factory()->tenant()->create();
        $this->actingAsTenant($school);
        [$section, $students] = $this->sectionWithStudents($school);

        $service = app(AttendanceService::class);

        $session = $service->takeRegister($section->getKey(), '2026-10-01', [
            ['student_id' => $students[0]->id, 'status' => Attendance::STATUS_PRESENT],
            ['student_id' => $students[1]->id, 'status' => Attendance::STATUS_ABSENT, 'absence_reason' => 'مرض'],
            ['student_id' => $students[2]->id, 'status' => Attendance::STATUS_LATE, 'late_minutes' => 10],
        ]);

        $this->assertSame(3, $session->attendances()->count());
        $this->assertSame(1, Attendance::query()->where('status', Attendance::STATUS_ABSENT)->count());

        // Re-taking the same register updates in place, never duplicates.
        $service->takeRegister($section->getKey(), '2026-10-01', [
            ['student_id' => $students[1]->id, 'status' => Attendance::STATUS_EXCUSED],
        ]);

        $this->assertSame(3, $session->refresh()->attendances()->count());
        $this->assertSame(0, Attendance::query()->where('status', Attendance::STATUS_ABSENT)->count());
    }

    public function test_section_report_summarises_the_range(): void
    {
        $school = Organization::factory()->tenant()->create();
        $this->actingAsTenant($school);
        [$section, $students] = $this->sectionWithStudents($school);

        $service = app(AttendanceService::class);

        $service->takeRegister($section->getKey(), '2026-10-01', [
            ['student_id' => $students[0]->id, 'status' => Attendance::STATUS_PRESENT],
            ['student_id' => $students[1]->id, 'status' => Attendance::STATUS_ABSENT],
        ]);

        $service->takeRegister($section->getKey(), '2026-10-02', [
            ['student_id' => $students[0]->id, 'status' => Attendance::STATUS_PRESENT],
            ['student_id' => $students[1]->id, 'status' => Attendance::STATUS_PRESENT],
        ]);

        $report = collect($service->sectionReport($section->getKey(), '2026-10-01', '2026-10-02'))
            ->keyBy('student_id');

        $this->assertSame(2, $report[$students[0]->id]['present']);
        $this->assertSame(100.0, $report[$students[0]->id]['rate']);
        $this->assertSame(1, $report[$students[1]->id]['absent']);
        $this->assertSame(50.0, $report[$students[1]->id]['rate']);
    }

    public function test_absence_alerts_raise_after_the_threshold(): void
    {
        $school = Organization::factory()->tenant()->create();
        $this->actingAsTenant($school);
        [$section, $students] = $this->sectionWithStudents($school);

        $service = app(AttendanceService::class);

        foreach (['2026-10-01', '2026-10-02', '2026-10-03'] as $date) {
            $service->takeRegister($section->getKey(), $date, [
                ['student_id' => $students[0]->id, 'status' => Attendance::STATUS_ABSENT],
            ]);
        }

        $alerts = $service->absenceAlerts('2026-10-01', '2026-10-03', threshold: 3);

        $this->assertCount(1, $alerts);
        $this->assertSame($students[0]->id, $alerts[0]['student_id']);
        $this->assertSame(3, $alerts[0]['absences']);
    }

    public function test_attendance_is_isolated_between_schools(): void
    {
        $schoolA = Organization::factory()->tenant()->create();
        $schoolB = Organization::factory()->tenant()->create();

        $this->actingAsTenant($schoolA);
        [$sectionA, $studentsA] = $this->sectionWithStudents($schoolA);
        app(AttendanceService::class)->takeRegister($sectionA->getKey(), '2026-10-01', [
            ['student_id' => $studentsA[0]->id, 'status' => Attendance::STATUS_PRESENT],
        ]);

        $this->actingAsTenant($schoolB);
        $this->assertSame(0, Attendance::query()->count());
    }
}

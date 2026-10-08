<?php

namespace Database\Seeders;

use App\Models\Academic\ClassSection;
use App\Models\Academic\Subject;
use App\Models\Academic\Term;
use App\Models\Exam\Exam;
use App\Models\Organization;
use App\Models\Student\Student;
use App\Services\Attendance\AttendanceService;
use App\Services\Exams\GradeService;
use App\Support\Tenancy\TenantManager;
use Illuminate\Database\Seeder;

/**
 * Seeds a few weeks of attendance and one exam per subject for each section,
 * then records marks — enough to produce meaningful reports and result sheets.
 */
class AcademicRecordsSeeder extends Seeder
{
    public function run(
        AttendanceService $attendance,
        GradeService $grades,
        TenantManager $tenants,
    ): void {
        $school = Organization::query()->where('type', Organization::TYPE_SCHOOL)->first();

        if (! $school) {
            return;
        }

        $tenants->setTenant($school);

        $term = Term::query()->orderBy('sequence')->first();

        foreach (ClassSection::query()->orderBy('id')->get() as $section) {
            $students = Student::query()
                ->where('class_section_id', $section->getKey())
                ->where('status', Student::STATUS_ENROLLED)
                ->get();

            if ($students->isEmpty()) {
                continue;
            }

            $this->seedAttendance($attendance, $section, $students);
            $this->seedExams($grades, $section, $students, $term?->getKey());
        }

        $this->command?->info('Attendance registers and exam marks seeded.');
    }

    protected function seedAttendance(AttendanceService $attendance, ClassSection $section, $students): void
    {
        // The last 10 school days (skipping Fridays).
        $day = now()->subDays(14);
        $seeded = 0;

        while ($seeded < 10) {
            if ($day->dayOfWeek !== 5) {
                $entries = $students->map(fn (Student $student) => [
                    'student_id' => $student->getKey(),
                    'status' => match (true) {
                        random_int(1, 10) === 1 => 'absent',
                        random_int(1, 10) === 2 => 'late',
                        default => 'present',
                    },
                ])->all();

                $attendance->takeRegister($section->getKey(), $day->toDateString(), $entries);
                $seeded++;
            }

            $day = $day->addDay();
        }
    }

    protected function seedExams(GradeService $grades, ClassSection $section, $students, ?int $termId): void
    {
        foreach (Subject::query()->orderBy('id')->get() as $subject) {
            $exam = Exam::create([
                'subject_id' => $subject->getKey(),
                'class_section_id' => $section->getKey(),
                'term_id' => $termId,
                'title' => 'اختبار شهري - '.$subject->name,
                'type' => Exam::TYPE_MONTHLY,
                'held_on' => now()->subDays(7)->toDateString(),
                'max_mark' => 100,
                'pass_mark' => (float) $subject->pass_mark,
                'is_published' => true,
            ]);

            $rows = $students->map(fn (Student $student) => [
                'student_id' => $student->getKey(),
                'mark' => random_int(40, 100),
                'is_absent' => false,
            ])->all();

            $grades->recordMany($exam, $rows);
        }
    }
}

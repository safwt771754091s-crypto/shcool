<?php

namespace Tests\Feature;

use App\Models\Academic\SchoolClass;
use App\Models\Academic\Subject;
use App\Models\Exam\Exam;
use App\Models\Exam\Grade;
use App\Models\Organization;
use App\Models\Student\Student;
use App\Services\Exams\GradeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamGradeTest extends TestCase
{
    use RefreshDatabase;

    protected function fixture(Organization $school): array
    {
        $class = SchoolClass::create(['name' => 'الصف الأول', 'grade' => 1]);
        $section = $class->sections()->create(['name' => 'أ']);

        $subject = Subject::create(['name' => 'الرياضيات', 'code' => 'MATH', 'pass_mark' => 50, 'max_mark' => 100]);

        $students = collect(range(1, 2))->map(fn (int $i) => Student::create([
            'class_section_id' => $section->getKey(),
            'student_number' => 'S-000'.$i,
            'full_name' => 'طالب '.$i,
            'status' => Student::STATUS_ENROLLED,
        ]));

        return [$section, $subject, $students];
    }

    public function test_grades_are_recorded_and_capped_by_max_mark(): void
    {
        $school = Organization::factory()->tenant()->create();
        $this->actingAsTenant($school);
        [$section, $subject, $students] = $this->fixture($school);

        $exam = Exam::create([
            'subject_id' => $subject->getKey(),
            'class_section_id' => $section->getKey(),
            'title' => 'اختبار شهري',
            'type' => Exam::TYPE_MONTHLY,
            'max_mark' => 100,
            'pass_mark' => 50,
        ]);

        $service = app(GradeService::class);

        $grade = $service->record($exam, $students[0], 80);
        $this->assertEquals(80, (float) $grade->mark);
        $this->assertTrue($grade->isPassing());

        $this->expectException(\InvalidArgumentException::class);
        $service->record($exam, $students[0], 120);
    }

    public function test_result_sheet_weights_subjects_and_ranks_the_section(): void
    {
        $school = Organization::factory()->tenant()->create();
        $this->actingAsTenant($school);
        [$section, $subject, $students] = $this->fixture($school);

        $exam = Exam::create([
            'subject_id' => $subject->getKey(),
            'class_section_id' => $section->getKey(),
            'title' => 'اختبار شهري',
            'max_mark' => 100,
            'pass_mark' => 50,
        ]);

        $service = app(GradeService::class);
        $service->recordMany($exam, [
            ['student_id' => $students[0]->id, 'mark' => 90],
            ['student_id' => $students[1]->id, 'mark' => 40],
        ]);

        $sheet = $service->resultSheet($students[0]);
        $this->assertSame(90.0, $sheet['overall_average']);
        $this->assertCount(1, $sheet['subjects']);
        $this->assertTrue($sheet['subjects'][0]['passed']);

        $sectionSheet = $service->sectionResultSheet($section->getKey());
        $this->assertSame($students[0]->id, $sectionSheet[0]['student_id']);
        $this->assertSame(1, $sectionSheet[0]['rank']);
        $this->assertSame($students[1]->id, $sectionSheet[1]['student_id']);
        $this->assertSame(2, $sectionSheet[1]['rank']);
        $this->assertFalse($sectionSheet[1]['subjects'][0]['passed']);
    }

    public function test_absent_student_scores_zero_without_a_mark(): void
    {
        $school = Organization::factory()->tenant()->create();
        $this->actingAsTenant($school);
        [$section, $subject, $students] = $this->fixture($school);

        $exam = Exam::create([
            'subject_id' => $subject->getKey(),
            'class_section_id' => $section->getKey(),
            'title' => 'اختبار',
            'max_mark' => 100,
            'pass_mark' => 50,
        ]);

        $grade = app(GradeService::class)->record($exam, $students[0], null, absent: true);

        $this->assertNull($grade->mark);
        $this->assertTrue($grade->is_absent);
        $this->assertFalse($grade->isPassing());
        $this->assertSame(0.0, app(GradeService::class)->resultSheet($students[0])['overall_average']);
    }

    public function test_grades_are_isolated_between_schools(): void
    {
        $schoolA = Organization::factory()->tenant()->create();
        $schoolB = Organization::factory()->tenant()->create();

        $this->actingAsTenant($schoolA);
        [$section, $subject, $students] = $this->fixture($schoolA);
        $exam = Exam::create([
            'subject_id' => $subject->getKey(),
            'class_section_id' => $section->getKey(),
            'title' => 'اختبار',
        ]);
        app(GradeService::class)->record($exam, $students[0], 70);

        $this->actingAsTenant($schoolB);
        $this->assertSame(0, Grade::query()->count());
    }
}

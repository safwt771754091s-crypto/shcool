<?php

namespace Tests\Feature;

use App\Models\Academic\ClassSection;
use App\Models\Academic\SchoolClass;
use App\Models\Organization;
use App\Models\Student\Admission;
use App\Models\Student\Guardian;
use App\Models\Student\Student;
use App\Services\Students\StudentService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentAdmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function section(Organization $school): ClassSection
    {
        $class = SchoolClass::create([
            'branch_id' => null,
            'name' => 'الصف الأول الابتدائي',
            'grade' => 1,
            'stage' => 'primary',
        ]);

        return $class->sections()->create(['name' => 'أ', 'capacity' => 30]);
    }

    public function test_student_is_enrolled_and_isolated_per_school(): void
    {
        $schoolA = Organization::factory()->tenant()->create();
        $schoolB = Organization::factory()->tenant()->create();

        $this->actingAsTenant($schoolA);
        $sectionA = $this->section($schoolA);

        $service = app(StudentService::class);

        $student = $service->enrol([
            'class_section_id' => $sectionA->getKey(),
            'student_number' => 'S-0001',
            'full_name' => 'علي محمد',
        ]);

        $this->assertSame(Student::STATUS_ENROLLED, $student->status);
        $this->assertSame($schoolA->id, $student->tenant_id);

        $this->actingAsTenant($schoolB);
        $this->assertSame(0, Student::query()->count());
    }

    public function test_admission_flows_from_submission_to_enrolment(): void
    {
        $school = Organization::factory()->tenant()->create();
        $this->actingAsTenant($school);
        $section = $this->section($school);

        $service = app(StudentService::class);

        $admission = Admission::create([
            'applicant_name' => 'يوسف حسين',
            'gender' => Student::GENDER_MALE,
        ]);

        $this->assertSame(Admission::STATUS_SUBMITTED, $admission->status);

        $service->decide($admission, true, note: 'مقبول');

        $this->assertSame(Admission::STATUS_ACCEPTED, $admission->refresh()->status);
        $this->assertNotNull($admission->decided_at);

        $student = $service->enrolFromAdmission($admission, [
            'class_section_id' => $section->getKey(),
            'student_number' => 'S-0002',
        ]);

        $this->assertSame(Student::STATUS_ENROLLED, $student->status);
        $this->assertSame('يوسف حسين', $student->full_name);
        $this->assertSame(Admission::STATUS_ENROLLED, $admission->refresh()->status);
        $this->assertSame($student->getKey(), $admission->student_id);
    }

    public function test_guardian_is_linked_to_a_student(): void
    {
        $school = Organization::factory()->tenant()->create();
        $this->actingAsTenant($school);
        $section = $this->section($school);

        $student = Student::create([
            'class_section_id' => $section->getKey(),
            'student_number' => 'S-0003',
            'full_name' => 'فاطمة أحمد',
        ]);

        $guardian = Guardian::create(['full_name' => 'أحمد', 'relation' => Guardian::RELATION_FATHER]);

        app(StudentService::class)->linkGuardian($student, $guardian, Guardian::RELATION_FATHER, true);

        $this->assertCount(1, $student->refresh()->guardians);
        $this->assertTrue((bool) $student->guardians->first()->pivot->is_primary);

        // Linking again is idempotent.
        app(StudentService::class)->linkGuardian($student, $guardian, Guardian::RELATION_FATHER, true);
        $this->assertCount(1, $student->refresh()->guardians);
    }

    public function test_student_number_is_unique_within_a_school(): void
    {
        $school = Organization::factory()->tenant()->create();
        $this->actingAsTenant($school);
        $section = $this->section($school);

        Student::create([
            'class_section_id' => $section->getKey(),
            'student_number' => 'S-0004',
            'full_name' => 'الأول',
        ]);

        $this->expectException(QueryException::class);

        Student::create([
            'class_section_id' => $section->getKey(),
            'student_number' => 'S-0004',
            'full_name' => 'الثاني',
        ]);
    }
}

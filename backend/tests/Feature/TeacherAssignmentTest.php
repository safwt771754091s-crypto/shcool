<?php

namespace Tests\Feature;

use App\Models\Academic\SchoolClass;
use App\Models\Academic\Subject;
use App\Models\Organization;
use App\Models\Staff\Teacher;
use App\Models\Staff\TeachingAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_is_idempotent_per_teacher_subject_section(): void
    {
        $school = Organization::factory()->tenant()->create();
        $this->actingAsTenant($school);

        $class = SchoolClass::create(['name' => 'الصف الأول', 'grade' => 1]);
        $section = $class->sections()->create(['name' => 'أ']);
        $subject = Subject::create(['name' => 'الرياضيات', 'code' => 'MATH']);

        $teacher = Teacher::create([
            'employee_number' => 'T-0001',
            'full_name' => 'أحمد الجبوري',
        ]);

        $attributes = [
            'teacher_id' => $teacher->getKey(),
            'subject_id' => $subject->getKey(),
            'class_section_id' => $section->getKey(),
            'academic_year_id' => null,
        ];

        TeachingAssignment::updateOrCreate($attributes, ['weekly_periods' => 5]);
        TeachingAssignment::updateOrCreate($attributes, ['weekly_periods' => 6]);

        $this->assertSame(1, TeachingAssignment::query()->count());
        $this->assertSame(6, TeachingAssignment::query()->first()->weekly_periods);
    }

    public function test_teachers_are_isolated_between_schools(): void
    {
        $schoolA = Organization::factory()->tenant()->create();
        $schoolB = Organization::factory()->tenant()->create();

        $this->actingAsTenant($schoolA);
        Teacher::create(['employee_number' => 'T-0001', 'full_name' => 'معلم أ']);

        $this->actingAsTenant($schoolB);
        $this->assertSame(0, Teacher::query()->count());

        Teacher::create(['employee_number' => 'T-0001', 'full_name' => 'معلم ب']);
        $this->assertSame(1, Teacher::query()->count());
    }
}

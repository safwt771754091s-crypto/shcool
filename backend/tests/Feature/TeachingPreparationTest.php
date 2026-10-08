<?php

namespace Tests\Feature;

use App\Models\Academic\ClassSection;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Subject;
use App\Models\Organization;
use App\Models\Teaching\CurriculumUnit;
use App\Models\Teaching\Lesson;
use App\Models\Teaching\LessonPreparation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeachingPreparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_preparation_is_stamped_with_the_active_tenant(): void
    {
        $school = Organization::factory()->tenant()->create();
        $this->actingAsTenant($school);

        $teacher = User::factory()->create(['tenant_id' => $school->id]);

        $subject = Subject::create(['name' => 'الرياضيات']);
        $unit = CurriculumUnit::create(['subject_id' => $subject->id, 'name' => 'الوحدة الأولى']);
        $lesson = $unit->lessons()->create(['title' => 'الجمع']);

        $preparation = LessonPreparation::create([
            'lesson_id' => $lesson->id,
            'teacher_id' => $teacher->id,
            'scheduled_on' => now()->toDateString(),
            'objectives' => 'أن يجمع الطالب الأعداد',
        ]);

        $this->assertSame($school->id, $preparation->tenant_id);
        $this->assertSame(LessonPreparation::STATUS_DRAFT, $preparation->status);
        $this->assertTrue($preparation->isEditable());
    }

    public function test_a_supervisor_can_approve_or_return_a_preparation(): void
    {
        $school = Organization::factory()->tenant()->create();
        $this->actingAsTenant($school);

        $teacher = User::factory()->create(['tenant_id' => $school->id]);
        $supervisor = User::factory()->create(['tenant_id' => $school->id]);

        $subject = Subject::create(['name' => 'اللغة الإنجليزية']);
        $unit = CurriculumUnit::create(['subject_id' => $subject->id, 'name' => 'Unit 1']);
        $lesson = $unit->lessons()->create(['title' => 'Greetings']);

        $preparation = LessonPreparation::create([
            'lesson_id' => $lesson->id,
            'teacher_id' => $teacher->id,
            'scheduled_on' => now()->toDateString(),
            'status' => LessonPreparation::STATUS_SUBMITTED,
        ]);

        $preparation->review(false, $supervisor, 'أضف الوسائل التعليمية.');
        $this->assertSame(LessonPreparation::STATUS_RETURNED, $preparation->refresh()->status);
        $this->assertTrue($preparation->isEditable());

        $preparation->review(true, $supervisor);
        $this->assertSame(LessonPreparation::STATUS_APPROVED, $preparation->refresh()->status);
        $this->assertFalse($preparation->isEditable());
        $this->assertSame($supervisor->id, $preparation->reviewed_by);
    }

    public function test_preparations_are_isolated_between_schools(): void
    {
        $schoolA = Organization::factory()->tenant()->create();
        $schoolB = Organization::factory()->tenant()->create();

        foreach ([$schoolA, $schoolB] as $school) {
            $this->actingAsTenant($school);

            $teacher = User::factory()->create(['tenant_id' => $school->id]);
            $subject = Subject::create(['name' => 'مادة '.$school->id]);
            $unit = CurriculumUnit::create(['subject_id' => $subject->id, 'name' => 'وحدة']);
            $lesson = $unit->lessons()->create(['title' => 'درس']);

            LessonPreparation::create([
                'lesson_id' => $lesson->id,
                'teacher_id' => $teacher->id,
                'scheduled_on' => now()->toDateString(),
            ]);
        }

        $this->actingAsTenant($schoolA);
        $this->assertSame(1, LessonPreparation::query()->count());
        $this->assertSame($schoolA->id, LessonPreparation::query()->first()->tenant_id);
    }

    public function test_classes_and_sections_are_scoped_to_the_school(): void
    {
        $school = Organization::factory()->tenant()->create();
        $this->actingAsTenant($school);

        $class = SchoolClass::create(['name' => 'الأول الابتدائي', 'grade' => 1, 'stage' => 'primary']);
        $section = $class->sections()->create(['name' => 'أ', 'capacity' => 30]);

        $this->assertSame($school->id, $class->tenant_id);
        $this->assertSame($school->id, $section->tenant_id);
        $this->assertSame($class->id, $section->schoolClass->id);
    }
}

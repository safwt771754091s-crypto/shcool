<?php

namespace Tests\Feature;

use App\Models\Academic\SchoolClass;
use App\Models\Academic\Subject;
use App\Models\Attendance\Attendance;
use App\Models\Exam\Exam;
use App\Models\Exam\Grade;
use App\Models\Organization;
use App\Models\Student\Student;
use App\Models\Sync\SyncBatch;
use App\Models\User;
use App\Services\Sync\SyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfflineSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function fixture(Organization $school, User $user): array
    {
        $class = SchoolClass::create(['name' => 'الصف الأول', 'grade' => 1]);
        $section = $class->sections()->create(['name' => 'أ']);
        $subject = Subject::create(['name' => 'الرياضيات', 'code' => 'MATH']);

        $student = Student::create([
            'class_section_id' => $section->getKey(),
            'student_number' => 'S-0001',
            'full_name' => 'علي محمد',
            'status' => Student::STATUS_ENROLLED,
        ]);

        $exam = Exam::create([
            'subject_id' => $subject->getKey(),
            'class_section_id' => $section->getKey(),
            'title' => 'اختبار شهري',
            'max_mark' => 100,
            'pass_mark' => 50,
        ]);

        return [$section, $student, $exam];
    }

    public function test_offline_batch_applies_attendance_and_grades(): void
    {
        $school = Organization::factory()->tenant()->create();
        $this->actingAsTenant($school);
        $user = User::factory()->create(['tenant_id' => $school->id]);
        [$section, $student, $exam] = $this->fixture($school, $user);

        $batch = app(SyncService::class)->apply($user, (string) Str::uuid(), [
            [
                'type' => 'attendance.register',
                'client_id' => 'a1',
                'payload' => [
                    'class_section_id' => $section->getKey(),
                    'attendance_date' => '2026-10-01',
                    'student_id' => $student->getKey(),
                    'status' => Attendance::STATUS_PRESENT,
                ],
            ],
            [
                'type' => 'grade.record',
                'client_id' => 'g1',
                'payload' => [
                    'exam_id' => $exam->getKey(),
                    'student_id' => $student->getKey(),
                    'mark' => 88,
                ],
            ],
        ], deviceId: 'device-1');

        $this->assertSame(SyncBatch::STATUS_APPLIED, $batch->status);
        $this->assertSame(2, $batch->applied_count);
        $this->assertSame(0, $batch->failed_count);
        $this->assertSame(1, Attendance::query()->count());
        $this->assertSame(1, Grade::query()->count());
        $this->assertSame($school->id, $batch->tenant_id);
    }

    public function test_replaying_the_same_batch_is_idempotent(): void
    {
        $school = Organization::factory()->tenant()->create();
        $this->actingAsTenant($school);
        $user = User::factory()->create(['tenant_id' => $school->id]);
        [$section, $student] = $this->fixture($school, $user);

        $batchId = (string) Str::uuid();
        $items = [[
            'type' => 'attendance.register',
            'payload' => [
                'class_section_id' => $section->getKey(),
                'attendance_date' => '2026-10-02',
                'student_id' => $student->getKey(),
                'status' => Attendance::STATUS_PRESENT,
            ],
        ]];

        $service = app(SyncService::class);
        $first = $service->apply($user, $batchId, $items);
        $second = $service->apply($user, $batchId, $items);

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame(1, SyncBatch::query()->count());
        $this->assertSame(1, Attendance::query()->count());
    }

    public function test_a_failing_item_does_not_roll_back_the_others(): void
    {
        $school = Organization::factory()->tenant()->create();
        $this->actingAsTenant($school);
        $user = User::factory()->create(['tenant_id' => $school->id]);
        [$section, $student] = $this->fixture($school, $user);

        $batch = app(SyncService::class)->apply($user, (string) Str::uuid(), [
            [
                'type' => 'attendance.register',
                'payload' => [
                    'class_section_id' => $section->getKey(),
                    'attendance_date' => '2026-10-03',
                    'student_id' => $student->getKey(),
                    'status' => Attendance::STATUS_PRESENT,
                ],
            ],
            [
                'type' => 'grade.record',
                'payload' => ['exam_id' => 999999, 'student_id' => $student->getKey(), 'mark' => 50],
            ],
        ]);

        $this->assertSame(SyncBatch::STATUS_PARTIAL, $batch->status);
        $this->assertSame(1, $batch->applied_count);
        $this->assertSame(1, $batch->failed_count);
        $this->assertSame(1, Attendance::query()->count());
    }

    public function test_sync_batches_are_isolated_between_schools(): void
    {
        $schoolA = Organization::factory()->tenant()->create();
        $schoolB = Organization::factory()->tenant()->create();

        $this->actingAsTenant($schoolA);
        $userA = User::factory()->create(['tenant_id' => $schoolA->id]);
        [$section, $student] = $this->fixture($schoolA, $userA);

        app(SyncService::class)->apply($userA, (string) Str::uuid(), [[
            'type' => 'attendance.register',
            'payload' => [
                'class_section_id' => $section->getKey(),
                'attendance_date' => '2026-10-04',
                'student_id' => $student->getKey(),
                'status' => Attendance::STATUS_PRESENT,
            ],
        ]]);

        $this->actingAsTenant($schoolB);
        $this->assertSame(0, SyncBatch::query()->count());
    }
}

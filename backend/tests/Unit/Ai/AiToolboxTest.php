<?php

namespace Tests\Unit\Ai;

use App\Models\Attendance\Attendance;
use App\Models\Finance\Invoice;
use App\Models\Organization;
use App\Models\Student\Student;
use App\Models\User;
use App\Services\Ai\AiToolbox;
use App\Support\Permission\RoleProvisioner;
use App\Support\Permission\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AiToolboxTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PermissionSeeder::class);

        $this->school = Organization::factory()->tenant()->create();
        app(RoleProvisioner::class)->provisionTenantRoles($this->school);
        $this->actingAsTenant($this->school);
    }

    protected function manager(): User
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->school->id);

        $user = User::factory()->forTenant($this->school->id)->create();
        $user->assignRole(Roles::SCHOOL_MANAGER);

        return $user;
    }

    public function test_student_count_tool_returns_real_numbers(): void
    {
        Student::create(['student_number' => 'S-1', 'full_name' => 'طالب 1', 'status' => Student::STATUS_ENROLLED]);
        Student::create(['student_number' => 'S-2', 'full_name' => 'طالب 2', 'status' => Student::STATUS_APPLICANT]);

        $result = app(AiToolbox::class)->run('student_count', ['group_by' => 'status'], $this->manager());

        $this->assertSame(2, $result['total']);
        $this->assertSame(1, $result['by_status'][Student::STATUS_ENROLLED]);
    }

    public function test_finance_summary_tool_aggregates_invoices(): void
    {
        $student = Student::create(['student_number' => 'S-9', 'full_name' => 'طالب', 'status' => Student::STATUS_ENROLLED]);

        Invoice::create([
            'student_id' => $student->id,
            'number' => 'INV-1',
            'title' => 'رسوم',
            'net_amount' => 1000,
            'paid_amount' => 400,
            'balance' => 600,
            'status' => 'partial',
            'issued_on' => now()->toDateString(),
        ]);

        $result = app(AiToolbox::class)->run('finance_summary', [], $this->manager());

        $this->assertSame(1000.0, $result['invoiced_total']);
        $this->assertSame(600.0, $result['outstanding_total']);
        $this->assertSame(1, $result['unpaid_invoices']);
    }

    public function test_attendance_summary_tool_counts_statuses(): void
    {
        $class = \App\Models\Academic\SchoolClass::create(['name' => 'الأول', 'grade' => 1]);
        $section = $class->sections()->create(['name' => 'أ']);

        $session = \App\Models\Attendance\AttendanceSession::create([
            'class_section_id' => $section->id,
            'attendance_date' => now()->toDateString(),
        ]);

        $statuses = [Attendance::STATUS_PRESENT, Attendance::STATUS_PRESENT, Attendance::STATUS_ABSENT];

        foreach ($statuses as $i => $status) {
            $student = Student::create([
                'class_section_id' => $section->id,
                'student_number' => 'S-'.$i,
                'full_name' => 'طالب '.$i,
                'status' => Student::STATUS_ENROLLED,
            ]);

            Attendance::create([
                'attendance_session_id' => $session->id,
                'student_id' => $student->id,
                'status' => $status,
            ]);
        }

        $result = app(AiToolbox::class)->run('attendance_summary', [], $this->manager());

        $this->assertSame(3, $result['total_records']);
        $this->assertSame(2, $result['by_status'][Attendance::STATUS_PRESENT]);
        $this->assertEqualsWithDelta(66.7, $result['attendance_rate'], 0.1);
    }

    public function test_tools_return_only_the_current_tenant_rows(): void
    {
        $this->actingAsTenant($this->school);
        Student::create(['student_number' => 'S-A', 'full_name' => 'طالب أ', 'status' => Student::STATUS_ENROLLED]);

        $other = Organization::factory()->tenant()->create();
        $this->actingAsTenant($other);
        Student::create(['student_number' => 'S-B', 'full_name' => 'طالب ب', 'status' => Student::STATUS_ENROLLED]);

        $this->actingAsTenant($this->school);
        $result = app(AiToolbox::class)->run('student_count', [], $this->manager());

        $this->assertSame(1, $result['total']);
    }

    public function test_a_parent_cannot_reach_a_management_tool(): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->school->id);
        $parent = User::factory()->forTenant($this->school->id)->create();
        $parent->assignRole(Roles::PARENT);

        $toolbox = app(AiToolbox::class);

        $this->assertFalse($toolbox->allows('finance_summary', $parent));
        $this->assertArrayHasKey('error', $toolbox->run('finance_summary', [], $parent));
    }

    public function test_tool_schemas_are_filtered_by_permission(): void
    {
        $schemas = app(AiToolbox::class)->schemas(
            ['student_count', 'national_overview'],
            $this->manager(),
        );

        $names = array_map(fn ($s) => $s['function']['name'], $schemas);

        $this->assertContains('student_count', $names);
        $this->assertNotContains('national_overview', $names);
    }
}

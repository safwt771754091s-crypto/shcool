<?php

namespace Tests\Feature;

use App\Models\Academic\SchoolClass;
use App\Models\Academic\Subject;
use App\Models\Organization;
use App\Models\Staff\Teacher;
use App\Models\Staff\TeachingAssignment;
use App\Models\User;
use App\Support\Permission\RoleProvisioner;
use App\Support\Permission\Roles;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TeacherDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_teacher_dashboard_aggregates_their_load(): void
    {
        $school = Organization::factory()->tenant()->create();
        $this->actingAsTenant($school);

        app(RoleProvisioner::class)->provisionTenantRoles($school);

        $user = User::factory()->create(['tenant_id' => $school->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);
        $user->assignRole(Roles::TEACHER);

        $class = SchoolClass::create(['name' => 'الأول', 'grade' => 1]);
        $section = $class->sections()->create(['name' => 'أ']);
        $subject = Subject::create(['name' => 'الرياضيات', 'code' => 'MATH']);

        $teacher = Teacher::create([
            'employee_number' => 'T-0001',
            'full_name' => 'أحمد الجبوري',
            'user_id' => $user->id,
        ]);

        TeachingAssignment::create([
            'teacher_id' => $teacher->getKey(),
            'subject_id' => $subject->getKey(),
            'class_section_id' => $section->getKey(),
            'weekly_periods' => 5,
            'is_homeroom' => true,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/teachers/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.teacher.full_name', 'أحمد الجبوري')
            ->assertJsonPath('data.assignments.total', 1)
            ->assertJsonPath('data.assignments.weekly_periods', 5)
            ->assertJsonPath('data.assignments.homeroom', 1)
            ->assertJsonPath('data.preparations.draft', 0)
            ->assertJsonPath('data.attendance.sessions_taken', 0)
            ->assertJsonPath('data.exams.created', 0);
    }

    public function test_dashboard_is_returned_as_null_for_a_non_teacher(): void
    {
        $school = Organization::factory()->tenant()->create();
        $this->actingAsTenant($school);

        app(RoleProvisioner::class)->provisionTenantRoles($school);

        // A principal can see teachers (and thus open the dashboard route) but
        // has no teacher profile linked to their account.
        $principal = User::factory()->create(['tenant_id' => $school->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);
        $principal->assignRole(Roles::SCHOOL_MANAGER);

        $token = $principal->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/teachers/dashboard')
            ->assertOk()
            ->assertJsonPath('data', null);
    }
}

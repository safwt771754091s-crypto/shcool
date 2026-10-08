<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Support\Permission\GlobalTeam;
use App\Support\Permission\Roles;
use App\Support\Permission\RoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PermissionSeeder::class);
    }

    public function test_global_roles_are_created_in_the_reserved_team(): void
    {
        $this->assertDatabaseHas('roles', [
            'name' => Roles::SUPER_ADMIN,
            'tenant_id' => GlobalTeam::ID,
        ]);
    }

    public function test_each_school_gets_its_own_copy_of_tenant_roles(): void
    {
        $schoolA = Organization::factory()->tenant()->create();
        $schoolB = Organization::factory()->tenant()->create();

        app(RoleProvisioner::class)->provisionTenantRoles($schoolA);
        app(RoleProvisioner::class)->provisionTenantRoles($schoolB);

        $this->assertDatabaseHas('roles', [
            'name' => Roles::TEACHER,
            'tenant_id' => $schoolA->id,
        ]);
        $this->assertDatabaseHas('roles', [
            'name' => Roles::TEACHER,
            'tenant_id' => $schoolB->id,
        ]);

        $this->assertSame(2, \App\Models\Role::query()
            ->where('name', Roles::TEACHER)
            ->count());
    }

    public function test_school_manager_role_has_expected_permissions(): void
    {
        $school = Organization::factory()->tenant()->create();
        app(RoleProvisioner::class)->provisionTenantRoles($school);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($school->id);

        $role = \App\Models\Role::query()
            ->where('name', Roles::SCHOOL_MANAGER)
            ->where('tenant_id', $school->id)
            ->firstOrFail();

        $this->assertTrue($role->hasPermissionTo('students.create'));
        $this->assertTrue($role->hasPermissionTo('fees.collect'));
    }

    public function test_teacher_role_is_scoped_to_attendance_and_grades(): void
    {
        $school = Organization::factory()->tenant()->create();
        app(RoleProvisioner::class)->provisionTenantRoles($school);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($school->id);

        $role = \App\Models\Role::query()
            ->where('name', Roles::TEACHER)
            ->where('tenant_id', $school->id)
            ->firstOrFail();

        $this->assertTrue($role->hasPermissionTo('attendance.create'));
        $this->assertTrue($role->hasPermissionTo('exams.grades.enter'));
        $this->assertFalse($role->hasPermissionTo('fees.collect'));
        $this->assertFalse($role->hasPermissionTo('users.create'));
    }

    public function test_user_keeps_roles_separate_per_tenant(): void
    {
        $schoolA = Organization::factory()->tenant()->create();
        $schoolB = Organization::factory()->tenant()->create();
        app(RoleProvisioner::class)->provisionTenantRoles($schoolA);
        app(RoleProvisioner::class)->provisionTenantRoles($schoolB);

        $registrar = app(PermissionRegistrar::class);

        $user = User::factory()->forTenant($schoolA->id)->create();

        $registrar->setPermissionsTeamId($schoolA->id);
        $user->assignRole(Roles::SCHOOL_MANAGER);

        // In school B the same user has no role yet.
        $registrar->setPermissionsTeamId($schoolB->id);
        $this->assertFalse($user->fresh()->hasRole(Roles::SCHOOL_MANAGER));

        // Back in school A the role is present.
        $registrar->setPermissionsTeamId($schoolA->id);
        $this->assertTrue($user->fresh()->hasRole(Roles::SCHOOL_MANAGER));
    }
}

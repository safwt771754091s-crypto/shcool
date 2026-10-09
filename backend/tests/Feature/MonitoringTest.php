<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Role;
use App\Models\Student\Student;
use App\Models\User;
use App\Support\Permission\GlobalTeam;
use App\Support\Permission\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The Minister of Education monitoring account and the read-only monitoring
 * endpoints (رابط المراقبة الشامل).
 */
class MonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PermissionSeeder::class);
    }

    protected function minister(): User
    {
        $user = User::factory()->platformAdmin()->create();

        app(PermissionRegistrar::class)->setPermissionsTeamId(GlobalTeam::ID);
        $user->assignRole(Role::query()
            ->where('name', Roles::MINISTER)
            ->where('tenant_id', GlobalTeam::ID)
            ->firstOrFail());

        return $user;
    }

    protected function schoolWithData(): Organization
    {
        $ministry = Organization::factory()->ministry()->create();
        $governorate = Organization::factory()->governorate()->childOf($ministry)->create();
        $directorate = Organization::factory()->directorate()->childOf($governorate)->create();
        $school = Organization::factory()->tenant()->childOf($directorate)->create();

        $this->actingAsTenant($school);
        Student::create(['student_number' => 'S-1', 'full_name' => 'طالب 1', 'status' => Student::STATUS_ENROLLED]);
        Student::create(['student_number' => 'S-2', 'full_name' => 'طالب 2', 'status' => Student::STATUS_ENROLLED]);
        Student::create(['student_number' => 'S-3', 'full_name' => 'طالب 3', 'status' => Student::STATUS_ENROLLED]);

        return $school;
    }

    public function test_minister_sees_the_whole_platform_roll_up(): void
    {
        $school = $this->schoolWithData();

        $response = $this->actingAs($this->minister())
            ->getJson('/api/v1/monitoring/overview');

        $response->assertOk()
            ->assertJsonPath('data.totals.schools', 1)
            ->assertJsonPath('data.totals.students', 3)
            ->assertJsonPath('data.per_school.0.id', $school->id)
            ->assertJsonPath('data.per_school.0.students', 3);
    }

    public function test_minister_can_read_the_full_tree(): void
    {
        $this->schoolWithData();

        $this->actingAs($this->minister())
            ->getJson('/api/v1/monitoring/tree')
            ->assertOk()
            ->assertJsonPath('data.0.type', Organization::TYPE_MINISTRY);
    }

    public function test_minister_is_read_only_and_cannot_create_organizations(): void
    {
        $ministry = Organization::factory()->ministry()->create();

        $this->actingAs($this->minister())
            ->postJson('/api/v1/organizations', [
                'parent_id' => $ministry->id,
                'type' => Organization::TYPE_GOVERNORATE,
                'name' => 'محافظة ممنوعة',
                'code' => 'GOV-NO',
            ])
            ->assertForbidden();
    }

    public function test_monitoring_requires_the_permission(): void
    {
        // A plain authenticated user without platform.monitor is rejected.
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/monitoring/overview')
            ->assertForbidden();
    }
}

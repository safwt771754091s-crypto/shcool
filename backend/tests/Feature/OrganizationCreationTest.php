<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Support\Permission\GlobalTeam;
use App\Support\Permission\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OrganizationCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PermissionSeeder::class);
    }

    protected function platformOwner(): User
    {
        $user = User::factory()->platformAdmin()->create();

        app(PermissionRegistrar::class)->setPermissionsTeamId(GlobalTeam::ID);
        $user->assignRole(Role::query()
            ->where('name', Roles::OWNER)
            ->where('tenant_id', GlobalTeam::ID)
            ->firstOrFail());

        return $user;
    }

    public function test_owner_can_create_a_governorate_under_the_ministry(): void
    {
        $ministry = Organization::factory()->ministry()->create();

        $response = $this->actingAs($this->platformOwner())
            ->postJson('/api/v1/organizations', [
                'parent_id' => $ministry->id,
                'type' => Organization::TYPE_GOVERNORATE,
                'name' => 'محافظة البصرة',
                'code' => 'GOV-BSR',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'governorate')
            ->assertJsonPath('data.code', 'GOV-BSR');

        // `slug` is NOT NULL, so it must be derived from the supplied code.
        $this->assertDatabaseHas('organizations', [
            'code' => 'GOV-BSR',
            'slug' => 'gov-bsr',
        ]);
    }

    public function test_creating_a_school_materialises_its_tenant_roles(): void
    {
        // Regression: provisioning a school's roles ran while Sanctum had
        // switched the runtime guard to `sanctum`, so role records were written
        // under a guard that had no permission catalogue and creation failed.
        $ministry = Organization::factory()->ministry()->create();
        $governorate = Organization::factory()->governorate()->childOf($ministry)->create();
        $directorate = Organization::factory()->directorate()->childOf($governorate)->create();

        $response = $this->actingAs($this->platformOwner())
            ->postJson('/api/v1/organizations', [
                'parent_id' => $directorate->id,
                'type' => Organization::TYPE_SCHOOL,
                'name' => 'مدرسة دجلة',
                'code' => 'SCH-DIJLA',
            ]);

        $response->assertCreated();

        $schoolId = $response->json('data.id');
        $this->assertSame($schoolId, $response->json('data.tenant_id'));

        // Every tenant role exists for the new school, all under the `web` guard.
        $this->assertDatabaseHas('roles', [
            'name' => Roles::SCHOOL_MANAGER,
            'tenant_id' => $schoolId,
            'guard_name' => 'web',
        ]);
        $this->assertSame(0, Role::query()
            ->where('tenant_id', $schoolId)
            ->where('guard_name', '!=', 'web')
            ->count());
    }

    public function test_a_branch_inherits_its_school_tenant(): void
    {
        $ministry = Organization::factory()->ministry()->create();
        $governorate = Organization::factory()->governorate()->childOf($ministry)->create();
        $directorate = Organization::factory()->directorate()->childOf($governorate)->create();
        $school = Organization::factory()->tenant()->childOf($directorate)->create();

        $response = $this->actingAs($this->platformOwner())
            ->postJson('/api/v1/organizations', [
                'parent_id' => $school->id,
                'type' => Organization::TYPE_BRANCH,
                'name' => 'فرع دجلة',
                'code' => 'SCH-DIJLA-B1',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.tenant_id', $school->id);
    }
}

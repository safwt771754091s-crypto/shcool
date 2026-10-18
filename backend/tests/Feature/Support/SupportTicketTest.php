<?php

namespace Tests\Feature\Support;

use App\Models\Organization;
use App\Models\Support\SupportTicket;
use App\Models\User;
use App\Support\Permission\RoleProvisioner;
use App\Support\Permission\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SupportTicketTest extends TestCase
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

    protected function staff(string $role): User
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->school->id);
        $user = User::factory()->forTenant($this->school->id)->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_a_parent_can_open_and_read_their_own_ticket(): void
    {
        $parent = $this->staff(Roles::PARENT);

        $id = $this->actingAs($parent)
            ->postJson('/api/v1/support/tickets', [
                'subject' => 'مشكلة في الدخول',
                'body' => 'لا أستطيع الدخول إلى الحساب.',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($parent)
            ->getJson("/api/v1/support/tickets/{$id}")
            ->assertOk()
            ->assertJsonPath('data.ticket.status', SupportTicket::STATUS_OPEN);
    }

    public function test_a_parent_cannot_see_the_whole_queue(): void
    {
        $parent = $this->staff(Roles::PARENT);
        $other = $this->staff(Roles::PARENT);

        $this->actingAs($other)->postJson('/api/v1/support/tickets', [
            'subject' => 'طلب',
            'body' => 'نص',
        ])->assertCreated();

        $this->actingAs($parent)
            ->getJson('/api/v1/support/tickets')
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_an_agent_can_reply_resolve_and_see_internal_notes(): void
    {
        $parent = $this->staff(Roles::PARENT);
        $manager = $this->staff(Roles::SCHOOL_MANAGER);

        $id = $this->actingAs($parent)->postJson('/api/v1/support/tickets', [
            'subject' => 'استفسار',
            'body' => 'لدي استفسار.',
        ])->json('data.id');

        $this->actingAs($manager)
            ->postJson("/api/v1/support/tickets/{$id}/messages", [
                'body' => 'ملاحظة داخلية',
                'internal' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.internal', true);

        $this->actingAs($manager)
            ->patchJson("/api/v1/support/tickets/{$id}", ['status' => SupportTicket::STATUS_RESOLVED])
            ->assertOk()
            ->assertJsonPath('data.status', SupportTicket::STATUS_RESOLVED);

        // The requester must not see the internal note.
        $this->actingAs($parent)
            ->getJson("/api/v1/support/tickets/{$id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.messages');
    }

    public function test_a_parent_cannot_manage_the_queue(): void
    {
        $parent = $this->staff(Roles::PARENT);

        $id = $this->actingAs($parent)->postJson('/api/v1/support/tickets', [
            'subject' => 'طلب',
            'body' => 'نص',
        ])->json('data.id');

        $this->actingAs($parent)
            ->patchJson("/api/v1/support/tickets/{$id}", ['priority' => 'urgent'])
            ->assertForbidden();
    }

    public function test_tickets_are_isolated_between_schools(): void
    {
        $manager = $this->staff(Roles::SCHOOL_MANAGER);

        $schoolB = Organization::factory()->tenant()->create();
        app(RoleProvisioner::class)->provisionTenantRoles($schoolB);

        $this->actingAsTenant($schoolB);
        app(PermissionRegistrar::class)->setPermissionsTeamId($schoolB->id);
        $userB = User::factory()->forTenant($schoolB->id)->create();
        $userB->assignRole(Roles::PARENT);
        $ticketB = SupportTicket::create([
            'user_id' => $userB->getKey(),
            'reference' => SupportTicket::newReference(),
            'subject' => 'طلب المدرسة ب',
            'last_message_at' => now(),
        ]);

        $this->actingAsTenant($this->school);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->school->id);

        $this->actingAs($manager)
            ->getJson("/api/v1/support/tickets/{$ticketB->id}")
            ->assertNotFound();
    }
}

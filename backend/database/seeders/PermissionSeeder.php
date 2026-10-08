<?php

namespace Database\Seeders;

use App\Support\Permission\RoleProvisioner;
use Illuminate\Database\Seeder;

/**
 * Creates the permission catalogue and the global roles. Tenant roles are
 * provisioned per school (see OrganizationSeeder / RoleProvisioner).
 */
class PermissionSeeder extends Seeder
{
    public function run(RoleProvisioner $provisioner): void
    {
        $provisioner->syncPermissions();
        $provisioner->syncGlobalRoles();

        $this->command?->info('Permissions and global roles provisioned.');
    }
}

<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Support\Permission\RoleProvisioner;
use Illuminate\Database\Seeder;

/**
 * Seeds a minimal but complete administrative tree:
 *
 *   وزارة التربية
 *     └── محافظة (بغداد)
 *           └── مديرية تربية
 *                 └── مدرسة
 *                       └── فرع
 *
 * Real deployments import the full tree from Excel; this is a working sample.
 */
class OrganizationSeeder extends Seeder
{
    public function run(RoleProvisioner $provisioner): void
    {
        $ministry = $this->make(null, Organization::TYPE_MINISTRY, 'وزارة التربية', 'MOE');

        $governorate = $this->make($ministry, Organization::TYPE_GOVERNORATE, 'محافظة بغداد', 'GOV-BGW');

        $directorate = $this->make($governorate, Organization::TYPE_DIRECTORATE, 'مديرية تربية بغداد/الرصافة الأولى', 'DIR-BGW-1');

        $school = $this->make($directorate, Organization::TYPE_SCHOOL, 'مدرسة النجاح الابتدائية', 'SCH-0001');
        $school->forceFill(['tenant_id' => $school->getKey()])->save();

        // Each school owns a full copy of the tenant-scoped roles.
        $provisioner->provisionTenantRoles($school);

        $this->make($school, Organization::TYPE_BRANCH, 'فرع الرصافة', 'SCH-0001-B1');
    }

    protected function make(?Organization $parent, string $type, string $name, string $code): Organization
    {
        $organization = new Organization([
            'type' => $type,
            'name' => $name,
            'code' => $code,
            'slug' => \Illuminate\Support\Str::slug($code),
        ]);

        $organization->parent_id = $parent?->getKey();
        $organization->refreshHierarchy();
        $organization->save();

        return $organization;
    }
}

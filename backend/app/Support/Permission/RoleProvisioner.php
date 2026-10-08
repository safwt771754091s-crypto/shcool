<?php

namespace App\Support\Permission;

use App\Models\Organization;
use App\Models\Permission as PermissionModel;
use App\Models\Role;
use App\Support\Tenancy\TenantManager;
use Illuminate\Support\Collection;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the permission catalogue and materialises roles.
 *
 * Permissions are global. Global roles (ministry/governorate/directorate) get
 * a null tenant_id. Tenant roles are created per school so that each school
 * owns and can customise its own role records.
 */
class RoleProvisioner
{
    public function __construct(
        protected TenantManager $tenants,
        protected PermissionRegistrar $registrar,
    ) {
    }

    /**
     * Create every permission defined in Permissions::all().
     */
    public function syncPermissions(): Collection
    {
        $created = collect();

        foreach (Permissions::all() as $name) {
            $created->push(PermissionModel::findOrCreate(
                $name,
                $this->guard(),
            ));
        }

        // Backfill the module column for grouping in the UI.
        PermissionModel::query()
            ->whereNull('module')
            ->get()
            ->each(function (PermissionModel $permission): void {
                $permission->forceFill([
                    'module' => Permissions::moduleOf($permission->name),
                ])->save();
            });

        $this->registrar->forgetCachedPermissions();

        return $created;
    }

    /**
     * Create or refresh the global (platform-level) roles.
     */
    public function syncGlobalRoles(): void
    {
        $this->tenants->withoutTenancy(function (): void {
            foreach (Roles::definitions() as $definition) {
                if (! $definition->global) {
                    continue;
                }

                $this->upsertRole($definition, tenantId: GlobalTeam::ID);
            }
        });
    }

    /**
     * Create the tenant-scoped roles for a single school.
     */
    public function provisionTenantRoles(Organization $school): void
    {
        $this->tenants->withoutTenancy(function () use ($school): void {
            foreach (Roles::definitions() as $definition) {
                if ($definition->global) {
                    continue;
                }

                $this->upsertRole($definition, tenantId: $school->getKey());
            }
        });
    }

    /**
     * Provision roles for every school in the platform.
     */
    public function provisionAllTenantRoles(): void
    {
        Organization::query()
            ->where('type', Organization::TYPE_SCHOOL)
            ->each(fn (Organization $school) => $this->provisionTenantRoles($school));
    }

    protected function upsertRole(RoleDefinition $definition, int $tenantId): Role
    {
        /** @var Role $role */
        $role = Role::query()->firstOrNew([
            'name' => $definition->name,
            'guard_name' => $this->guard(),
            'tenant_id' => $tenantId,
        ]);

        $role->fill([
            'display_name' => $definition->displayName,
            'level' => $definition->level,
            'is_system' => $definition->system,
        ])->save();

        $role->syncPermissions($this->expandPermissions($definition->permissions));

        return $role;
    }

    /**
     * Expand wildcard permission patterns into concrete permission names.
     *
     * @param  list<string>  $patterns
     * @return list<string>
     */
    protected function expandPermissions(array $patterns): array
    {
        $all = Permissions::all();

        $resolved = [];

        foreach ($patterns as $pattern) {
            if ($pattern === '*') {
                return $all;
            }

            if (str_ends_with($pattern, '.*')) {
                $module = substr($pattern, 0, -2);
                foreach ($all as $permission) {
                    if (str_starts_with($permission, $module.'.')) {
                        $resolved[] = $permission;
                    }
                }

                continue;
            }

            $resolved[] = $pattern;
        }

        return array_values(array_unique($resolved));
    }

    protected function guard(): string
    {
        return config('auth.defaults.guard', 'web');
    }
}

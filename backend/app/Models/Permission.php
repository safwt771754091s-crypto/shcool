<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Permissions are global (not tenant-scoped): a permission such as
 * "students.create" means the same thing in every school. Only the roles that
 * hold it are tenant-scoped.
 */
class Permission extends SpatiePermission
{
    /**
     * Permissions live under a single guard. Pin it so runtime guard switching
     * (Sanctum sets the default guard to `sanctum`) never splits the catalogue.
     */
    protected $guard_name = 'web';

    protected $fillable = [
        'name',
        'guard_name',
        'module',
    ];
}

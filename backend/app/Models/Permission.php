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
    protected $fillable = [
        'name',
        'guard_name',
        'module',
    ];
}

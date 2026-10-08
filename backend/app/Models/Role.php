<?php

namespace App\Models;

use App\Support\Permission\GlobalTeam;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Tenant-aware role. Roles are scoped to a school via the tenant_id column
 * (spatie's team foreign key), so "school_manager" in school A is a different
 * record from "school_manager" in school B.
 */
class Role extends SpatieRole
{
    protected $fillable = [
        'name',
        'guard_name',
        'tenant_id',
        'display_name',
        'level',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'level' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'tenant_id');
    }

    /**
     * Roles that are not bound to a single school (ministry/governorate
     * level roles). They live in the reserved global team and apply
     * platform-wide.
     */
    public function scopeGlobal(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('tenant_id', GlobalTeam::ID);
    }

    /**
     * Roles that belong to an actual school.
     */
    public function scopeTenantScoped(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('tenant_id', '!=', GlobalTeam::ID);
    }
}

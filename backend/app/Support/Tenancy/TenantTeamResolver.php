<?php

namespace App\Support\Tenancy;

use App\Support\Permission\GlobalTeam;
use App\Support\Tenancy\Contracts\TenantResolver;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Contracts\PermissionsTeamResolver;

/**
 * Bridges spatie/laravel-permission's "teams" feature to our TenantManager.
 *
 * The package instantiates this class with `new` and no arguments, so the
 * tenant manager is resolved lazily from the container. Resolving it eagerly
 * would be circular: TenantManager depends on PermissionRegistrar, which
 * constructs this resolver.
 */
class TenantTeamResolver implements PermissionsTeamResolver
{
    /**
     * Explicit override, used by seeders and admin tooling that need to act on
     * a specific school outside a request. A separate flag distinguishes an
     * explicit "no team" (global roles) from "not set".
     */
    protected int|string|null $override = null;

    protected bool $hasOverride = false;

    public function getPermissionsTeamId(): int|string|null
    {
        if ($this->hasOverride) {
            return $this->override;
        }

        // No active tenant means platform scope, which maps to the reserved
        // global team id (the column is part of a primary key and cannot be NULL).
        return Container::getInstance()
            ->make(TenantResolver::class)
            ->resolve()
            ?->getKey() ?? GlobalTeam::ID;
    }

    public function setPermissionsTeamId(int|string|Model|null $id): void
    {
        $this->override = $id instanceof Model ? $id->getKey() : $id;
        $this->hasOverride = true;
    }
}

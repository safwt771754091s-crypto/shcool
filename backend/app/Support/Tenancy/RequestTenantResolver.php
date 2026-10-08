<?php

namespace App\Support\Tenancy;

use App\Models\Organization;
use App\Support\Tenancy\Contracts\TenantResolver;
use Illuminate\Http\Request;

/**
 * Resolves the active tenant from the authenticated user.
 *
 * Rules:
 *  - Platform admins may switch tenant with the X-Tenant-Id header.
 *  - Regular users are locked to their own tenant_id.
 *  - A user with no tenant (pure platform staff) resolves to null.
 */
class RequestTenantResolver implements TenantResolver
{
    public function __construct(protected Request $request)
    {
    }

    public function resolve(): ?Organization
    {
        $user = $this->request->user();

        if ($user === null) {
            return null;
        }

        $model = config('tenancy.tenant_model', Organization::class);

        if ($this->isPlatformAdmin($user)) {
            $headerId = $this->request->header(config('tenancy.header'));

            if ($headerId !== null && $headerId !== '') {
                return $model::query()->find($headerId);
            }

            // Platform admin without an explicit tenant -> no active tenant.
            return null;
        }

        $tenantId = $user->tenant_id ?? null;

        return $tenantId ? $model::query()->find($tenantId) : null;
    }

    protected function isPlatformAdmin($user): bool
    {
        return (bool) ($user->is_platform_admin ?? false);
    }
}

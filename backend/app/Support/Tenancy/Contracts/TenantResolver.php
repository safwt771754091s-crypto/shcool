<?php

namespace App\Support\Tenancy\Contracts;

use App\Models\Organization;

interface TenantResolver
{
    /**
     * Resolve the active tenant for the current request/context, or null when
     * no tenant can be determined (e.g. unauthenticated or platform staff).
     */
    public function resolve(): ?Organization;
}

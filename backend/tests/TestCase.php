<?php

namespace Tests;

use App\Models\Organization;
use App\Support\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Activate a tenant for the current test process.
     */
    protected function actingAsTenant(Organization $tenant): static
    {
        app(TenantManager::class)->setTenant($tenant);

        return $this;
    }

    protected function tenantManager(): TenantManager
    {
        return app(TenantManager::class);
    }
}

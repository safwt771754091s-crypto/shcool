<?php

namespace Tests;

use App\Models\Organization;
use App\Support\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Never let a test reach a real LLM gateway: the environment may carry
        // a key-refresh URL, which would otherwise make "unconfigured" tests
        // fetch a live key. Individual tests opt back in by setting ai.api_key.
        config()->set('ai.key_refresh_url', null);
    }

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

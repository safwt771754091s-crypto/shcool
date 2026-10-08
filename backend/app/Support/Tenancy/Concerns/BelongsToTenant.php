<?php

namespace App\Support\Tenancy\Concerns;

use App\Models\Organization;
use App\Support\Tenancy\Scopes\TenantScope;
use App\Support\Tenancy\TenantManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Adds tenant isolation to a model.
 *
 * - Registers the TenantScope global scope (reads are filtered).
 * - Stamps tenant_id on create when it is missing (writes are attributed).
 * - Exposes helpers to query across all tenants deliberately.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(
            new TenantScope(app(TenantManager::class), static::tenantColumn())
        );

        static::creating(function ($model): void {
            app(TenantManager::class)->stampTenant($model, static::tenantColumn());
        });
    }

    public static function tenantColumn(): string
    {
        return 'tenant_id';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(config('tenancy.tenant_model', Organization::class), static::tenantColumn());
    }

    /**
     * Query every tenant's rows. Only for platform-level reporting and admin.
     */
    public static function allTenants(): Builder
    {
        return static::withoutGlobalScope(TenantScope::class);
    }
}

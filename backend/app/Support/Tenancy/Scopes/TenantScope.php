<?php

namespace App\Support\Tenancy\Scopes;

use App\Support\Tenancy\TenantManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope that restricts every query on a tenant-aware model to the
 * currently active tenant. Registered automatically by BelongsToTenant.
 */
class TenantScope implements Scope
{
    public function __construct(
        protected TenantManager $tenants,
        protected string $column = 'tenant_id',
    ) {
    }

    public function apply(Builder $builder, Model $model): void
    {
        $this->tenants->applyScope($builder, $this->column);
    }
}

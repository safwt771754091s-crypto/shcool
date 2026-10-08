<?php

namespace App\Support\Tenancy;

use App\Models\Organization;
use App\Support\Tenancy\Contracts\TenantResolver;
use App\Support\Tenancy\Exceptions\TenantNotResolvedException;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Holds the active tenant for the current process and applies the tenant
 * constraint to every query built by tenant-scoped models.
 *
 * Role checks are tenant-scoped separately: spatie/laravel-permission asks the
 * TenantTeamResolver for the current team, and that resolver reads the active
 * tenant lazily from the request. Nothing has to be pushed here, which keeps
 * resolution correct even though the tenant is only known after the auth
 * middleware has run.
 */
class TenantManager
{
    protected ?Organization $tenant = null;

    protected bool $resolved = false;

    protected bool $bypass = false;

    public function __construct(
        protected TenantResolver $resolver,
    ) {
    }

    public function setTenant(?Organization $tenant): void
    {
        $this->tenant = $tenant;
        $this->resolved = true;
    }

    public function setTenantId(int|string|null $tenantId): void
    {
        if ($tenantId === null) {
            $this->setTenant(null);

            return;
        }

        $this->setTenant(
            $this->tenantModel()::query()->find($tenantId)
        );
    }

    /**
     * Lazily resolve the tenant from the current request the first time it is
     * needed, then cache the result for the rest of the process.
     */
    public function tenant(): ?Organization
    {
        if (! $this->resolved) {
            $this->setTenant($this->resolver->resolve());
        }

        return $this->tenant;
    }

    public function tenantId(): int|string|null
    {
        return $this->tenant()?->getKey();
    }

    public function hasTenant(): bool
    {
        return $this->tenant() !== null;
    }

    /**
     * Run a callback with tenancy disabled. Use for cross-tenant operations
     * such as platform administration, reporting and seeders.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function withoutTenancy(Closure $callback): mixed
    {
        $previous = $this->bypass;
        $this->bypass = true;

        try {
            return $callback();
        } finally {
            $this->bypass = $previous;
        }
    }

    public function isBypassed(): bool
    {
        return $this->bypass;
    }

    /**
     * Apply the tenant constraint to a query. Called by the global scope.
     *
     * When `$includeGlobal` is true the model may also hold platform-wide rows
     * (tenant_id IS NULL) — ministry cups, national activity templates, app
     * templates — and those stay visible to every tenant.
     */
    public function applyScope(Builder $builder, string $column = 'tenant_id', bool $includeGlobal = false): void
    {
        if ($this->isBypassed()) {
            return;
        }

        $qualified = $builder->getModel()->qualifyColumn($column);
        $tenantId = $this->tenantId();

        if ($tenantId === null) {
            if ($includeGlobal) {
                $builder->whereNull($qualified);

                return;
            }

            if (config('tenancy.strict')) {
                throw TenantNotResolvedException::forModel($builder->getModel()::class);
            }

            // Non-strict: return nothing rather than leaking every tenant's rows.
            $builder->whereRaw('1 = 0');

            return;
        }

        if ($includeGlobal) {
            $builder->where(
                fn (Builder $q) => $q->where($qualified, $tenantId)->orWhereNull($qualified)
            );

            return;
        }

        $builder->where($qualified, $tenantId);
    }

    public function tenantModel(): string
    {
        return config('tenancy.tenant_model', Organization::class);
    }

    /**
     * Assign the active tenant to a model before it is persisted so callers do
     * not have to set tenant_id manually.
     */
    public function stampTenant(Model $model, string $column = 'tenant_id'): void
    {
        if ($this->isBypassed()) {
            return;
        }

        if ($model->getAttribute($column) === null && $this->hasTenant()) {
            $model->setAttribute($column, $this->tenantId());
        }
    }
}

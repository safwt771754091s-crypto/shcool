<?php

namespace App\Providers;

use App\Support\Tenancy\Contracts\TenantResolver;
use App\Support\Tenancy\RequestTenantResolver;
use App\Support\Tenancy\TenantManager;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantResolver::class, RequestTenantResolver::class);
        $this->app->singleton(TenantManager::class, function ($app) {
            return new TenantManager($app->make(TenantResolver::class));
        });

        $this->app->alias(TenantManager::class, 'tenancy');
    }

    public function boot(): void
    {
        //
    }
}

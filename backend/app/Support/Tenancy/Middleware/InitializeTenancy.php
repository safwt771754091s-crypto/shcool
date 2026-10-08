<?php

namespace App\Support\Tenancy\Middleware;

use App\Support\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves and activates the tenant for the current request, then resets it
 * after the response so long-running workers (queues/octane) never leak the
 * tenant of one request into the next.
 */
class InitializeTenancy
{
    public function __construct(protected TenantManager $tenants) {}

    public function handle(Request $request, Closure $next): Response
    {
        // This middleware runs before `auth:sanctum`, so the user (and therefore
        // the tenant) is not known yet. Clear any cached resolution and let the
        // first tenant-scoped query resolve it lazily, after auth has run.
        $this->tenants->forget();

        try {
            return $next($request);
        } finally {
            $this->tenants->setTenant(null);
        }
    }
}

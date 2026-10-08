<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tenant Model
    |--------------------------------------------------------------------------
    | The Eloquent model that represents a tenant. A tenant is always a
    | school-level organization; branches share their school's tenant_id.
    */

    'tenant_model' => App\Models\Organization::class,

    /*
    |--------------------------------------------------------------------------
    | Tenant Level
    |--------------------------------------------------------------------------
    | The organization type that acts as the tenant boundary. Everything at or
    | below this level is isolated by tenant_id.
    */

    'tenant_type' => 'school',

    /*
    |--------------------------------------------------------------------------
    | Strict Mode
    |--------------------------------------------------------------------------
    | When true, querying a tenant-scoped model without an active tenant throws
    | TenantNotResolvedException instead of returning unscoped results. Keep it
    | disabled only in local/console contexts (seeders, migrations).
    */

    'strict' => env('TENANCY_STRICT', false),

    /*
    |--------------------------------------------------------------------------
    | Tenant Header
    |--------------------------------------------------------------------------
    | Request header a platform administrator may use to switch the active
    | tenant. Regular users are always locked to their own tenant.
    */

    'header' => 'X-Tenant-Id',

];

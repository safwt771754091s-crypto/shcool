<?php

namespace App\Support\Tenancy\Exceptions;

use RuntimeException;

class TenantNotResolvedException extends RuntimeException
{
    public static function forModel(string $model): self
    {
        return new self(
            "Attempted to query tenant-scoped model [{$model}] without an active tenant. "
            .'Set a tenant or wrap the query in TenantManager::withoutTenancy().'
        );
    }
}

<?php

namespace App\Support\Audit;

use App\Models\AuditLog;
use App\Support\Tenancy\TenantManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Writes security-relevant events to the append-only audit trail.
 *
 * Usage: app(AuditLogger::class)->log('auth.login', $user, description: '...');
 */
class AuditLogger
{
    public function __construct(
        protected Request $request,
        protected TenantManager $tenants,
    ) {
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function log(
        string $event,
        ?Model $auditable = null,
        ?string $description = null,
        array $oldValues = [],
        array $newValues = [],
        ?int $userId = null,
    ): AuditLog {
        $user = $this->request->user();

        return AuditLog::create([
            'tenant_id' => $this->tenants->tenantId(),
            'user_id' => $userId ?? $user?->getKey(),
            'event' => $event,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'description' => $description,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => $this->request->ip(),
            'user_agent' => substr((string) $this->request->userAgent(), 0, 255),
        ]);
    }
}

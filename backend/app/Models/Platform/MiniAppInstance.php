<?php

namespace App\Models\Platform;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Not covered by the tenant global scope on purpose: an instance is addressed
 * by (app, organization) and lives at every rung of the hierarchy, including
 * the global directorate/governorate/ministry levels. Access is filtered by
 * organization ancestry in MiniAppService, and tenant_id is kept only as a
 * denormalised column for reporting.
 */
class MiniAppInstance extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'mini_app_id',
        'organization_id',
        'scope_type',
        'settings',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_enabled' => 'boolean',
        ];
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(MiniApp::class, 'mini_app_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(config('tenancy.tenant_model', Organization::class), 'tenant_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }
}

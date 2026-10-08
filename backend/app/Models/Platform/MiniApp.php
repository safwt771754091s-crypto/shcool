<?php

namespace App\Models\Platform;

use App\Models\Organization;
use App\Support\Competition\CompetitionScope;
use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A mini-app template. Every school, directorate, governorate and the ministry
 * can publish its own instance of it.
 */
class MiniApp extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'category',
        'description',
        'icon',
        'color',
        'supported_scopes',
        'is_published',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'supported_scopes' => 'array',
            'is_published' => 'boolean',
        ];
    }


    /**
     * Platform-wide rows (tenant_id IS NULL) stay visible to every tenant.
     */
    public static function includesGlobalRows(): bool
    {
        return true;
    }
    public function instances(): HasMany
    {
        return $this->hasMany(MiniAppInstance::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    /**
     * @return list<string>
     */
    public function scopes(): array
    {
        return $this->supported_scopes ?: CompetitionScope::LADDER;
    }
}

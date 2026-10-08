<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * The users table is intentionally NOT covered by the tenant global scope.
 *
 * The active tenant is derived from the authenticated user, so scoping the
 * user query by tenant would be circular (the guard cannot load the user
 * before the tenant is known). Isolation for accounts is therefore enforced
 * explicitly: `forTenant()` plus tenant-aware controllers/policies.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'phone',
        'national_id',
        'avatar',
        'password',
        'is_active',
        'is_platform_admin',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_platform_admin' => 'boolean',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    // ---------------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------------

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(config('tenancy.tenant_model', Organization::class), 'tenant_id');
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
            ->withPivot(['job_title', 'is_primary'])
            ->withTimestamps();
    }

    /**
     * Restrict a user query to a single school. Use this in every tenant-aware
     * controller instead of relying on a global scope.
     */
    public function scopeForTenant(Builder $query, int|string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    // ---------------------------------------------------------------------
    // Tenancy / authorization helpers
    // ---------------------------------------------------------------------

    /**
     * Platform staff bypass tenant isolation and may operate across schools.
     */
    public function isPlatformAdmin(): bool
    {
        return $this->is_platform_admin === true;
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null
            && $this->two_factor_confirmed_at !== null;
    }
}

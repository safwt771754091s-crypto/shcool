<?php

namespace App\Models\Activities;

use App\Models\User;
use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InteractiveActivity extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    public const AREA_ENGLISH = 'english';

    public const AREA_MATH = 'math';

    public const AREA_ACTIVITIES = 'activities';

    protected $fillable = [
        'tenant_id',
        'title',
        'subject_area',
        'kind',
        'grade',
        'time_limit_seconds',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'grade' => 'integer',
            'time_limit_seconds' => 'integer',
            'is_active' => 'boolean',
        ];
    }


    /**
     * Platform-wide rows (tenant_id IS NULL) stay visible to every tenant.
     */
    public static function includesGlobalRows(): bool
    {
        return true;
    }
    public function questions(): HasMany
    {
        return $this->hasMany(ActivityQuestion::class)->orderBy('sequence');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ActivityAttempt::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

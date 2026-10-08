<?php

namespace App\Models\Staff;

use App\Models\User;
use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Teacher extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_LEAVE = 'leave';

    public const STATUS_RETIRED = 'retired';

    public const STATUS_TERMINATED = 'terminated';

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
    ];

    protected $fillable = [
        'tenant_id',
        'user_id',
        'employee_number',
        'full_name',
        'gender',
        'birth_date',
        'national_id',
        'phone',
        'email',
        'specialization',
        'job_title',
        'qualification',
        'hired_on',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'hired_on' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }
}

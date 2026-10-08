<?php

namespace App\Models\Student;

use App\Models\User;
use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A parent / guardian account (ولي أمر) that can use the parent portal.
 */
class Guardian extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    public const RELATION_FATHER = 'father';

    public const RELATION_MOTHER = 'mother';

    public const RELATION_GUARDIAN = 'guardian';

    protected $attributes = [
        'relation' => self::RELATION_FATHER,
        'is_active' => true,
    ];

    protected $fillable = [
        'tenant_id',
        'user_id',
        'full_name',
        'relation',
        'phone',
        'alt_phone',
        'email',
        'national_id',
        'job_title',
        'address',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'guardian_student')
            ->withPivot(['relation', 'is_primary', 'can_pickup'])
            ->withTimestamps();
    }
}

<?php

namespace App\Models\Student;

use App\Models\Academic\ClassSection;
use App\Models\Attendance\Attendance;
use App\Models\Exam\Grade;
use App\Models\User;
use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    public const STATUS_APPLICANT = 'applicant';

    public const STATUS_ENROLLED = 'enrolled';

    public const STATUS_GRADUATED = 'graduated';

    public const STATUS_WITHDRAWN = 'withdrawn';

    public const STATUS_TRANSFERRED = 'transferred';

    public const GENDER_MALE = 'male';

    public const GENDER_FEMALE = 'female';

    protected $attributes = [
        'status' => self::STATUS_APPLICANT,
    ];

    protected $fillable = [
        'tenant_id',
        'user_id',
        'class_section_id',
        'student_number',
        'full_name',
        'gender',
        'birth_date',
        'national_id',
        'phone',
        'address',
        'photo',
        'status',
        'admitted_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'admitted_at' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class, 'class_section_id');
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'guardian_student')
            ->withPivot(['relation', 'is_primary', 'can_pickup'])
            ->withTimestamps();
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    public function isEnrolled(): bool
    {
        return $this->status === self::STATUS_ENROLLED;
    }
}

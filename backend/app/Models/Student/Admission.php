<?php

namespace App\Models\Student;

use App\Models\Academic\AcademicYear;
use App\Models\Academic\SchoolClass;
use App\Models\User;
use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An admission application (طلب قبول) awaiting a decision.
 */
class Admission extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_ENROLLED = 'enrolled';

    protected $attributes = [
        'status' => self::STATUS_SUBMITTED,
    ];

    protected $fillable = [
        'tenant_id',
        'academic_year_id',
        'school_class_id',
        'student_id',
        'applicant_name',
        'gender',
        'birth_date',
        'national_id',
        'guardian_name',
        'guardian_phone',
        'previous_school',
        'status',
        'score',
        'decision_note',
        'decided_by',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'decided_at' => 'datetime',
            'score' => 'integer',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isDecided(): bool
    {
        return in_array($this->status, [self::STATUS_ACCEPTED, self::STATUS_REJECTED], true);
    }
}

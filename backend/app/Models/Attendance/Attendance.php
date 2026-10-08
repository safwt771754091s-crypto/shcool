<?php

namespace App\Models\Attendance;

use App\Models\Student\Student;
use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single student's attendance on one roll call.
 */
class Attendance extends Model
{
    use BelongsToTenant, HasFactory;

    public const STATUS_PRESENT = 'present';

    public const STATUS_ABSENT = 'absent';

    public const STATUS_LATE = 'late';

    public const STATUS_EXCUSED = 'excused';

    public const STATUSES = [
        self::STATUS_PRESENT,
        self::STATUS_ABSENT,
        self::STATUS_LATE,
        self::STATUS_EXCUSED,
    ];

    protected $attributes = [
        'status' => self::STATUS_PRESENT,
    ];

    protected $fillable = [
        'tenant_id',
        'attendance_session_id',
        'student_id',
        'status',
        'late_minutes',
        'absence_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return ['late_minutes' => 'integer'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'attendance_session_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function isPresent(): bool
    {
        return $this->status === self::STATUS_PRESENT;
    }
}

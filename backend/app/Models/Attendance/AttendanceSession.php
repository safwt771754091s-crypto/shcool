<?php

namespace App\Models\Attendance;

use App\Models\Academic\ClassSection;
use App\Models\User;
use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One roll call for a section on a given day/period.
 */
class AttendanceSession extends Model
{
    use BelongsToTenant, HasFactory;

    protected $attributes = [
        'period' => 'daily',
    ];

    protected $fillable = [
        'tenant_id',
        'class_section_id',
        'attendance_date',
        'period',
        'taken_by',
        'submitted_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'submitted_at' => 'datetime',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class, 'class_section_id');
    }

    public function takenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'taken_by');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}

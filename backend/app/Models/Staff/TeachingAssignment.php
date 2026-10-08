<?php

namespace App\Models\Staff;

use App\Models\Academic\AcademicYear;
use App\Models\Academic\ClassSection;
use App\Models\Academic\Subject;
use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Assigns a teacher to a (subject, section) pair — the unit every attendance
 * register, exam and timetable entry is keyed on.
 */
class TeachingAssignment extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $attributes = [
        'weekly_periods' => 1,
        'is_homeroom' => false,
        'is_active' => true,
    ];

    protected $fillable = [
        'tenant_id',
        'teacher_id',
        'subject_id',
        'class_section_id',
        'academic_year_id',
        'weekly_periods',
        'is_homeroom',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'weekly_periods' => 'integer',
            'is_homeroom' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class, 'class_section_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}

<?php

namespace App\Models\Exam;

use App\Models\Student\Student;
use App\Models\User;
use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One student's mark in one exam.
 */
class Grade extends Model
{
    use BelongsToTenant, HasFactory;

    protected $attributes = [
        'is_absent' => false,
    ];

    protected $fillable = [
        'tenant_id',
        'exam_id',
        'student_id',
        'mark',
        'is_absent',
        'notes',
        'entered_by',
        'entered_at',
    ];

    protected function casts(): array
    {
        return [
            'mark' => 'decimal:2',
            'is_absent' => 'boolean',
            'entered_at' => 'datetime',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function isPassing(): bool
    {
        return ! $this->is_absent && $this->mark !== null && (float) $this->mark >= (float) $this->exam->pass_mark;
    }
}

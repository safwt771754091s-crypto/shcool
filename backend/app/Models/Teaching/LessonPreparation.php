<?php

namespace App\Models\Teaching;

use App\Models\Academic\ClassSection;
use App\Models\User;
use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A row in the teacher's preparation notebook (كراسة التحضير).
 */
class LessonPreparation extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_RETURNED = 'returned';

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    protected $fillable = [
        'tenant_id',
        'lesson_id',
        'teacher_id',
        'class_section_id',
        'scheduled_on',
        'status',
        'objectives',
        'strategies',
        'resources',
        'homework',
        'assessment',
        'notes',
        'reviewed_by',
        'reviewed_at',
        'review_comment',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_on' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class, 'class_section_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_RETURNED], true);
    }

    /**
     * Supervisor decision: approve or return with a comment.
     */
    public function review(bool $approved, User $reviewer, ?string $comment = null): void
    {
        $this->forceFill([
            'status' => $approved ? self::STATUS_APPROVED : self::STATUS_RETURNED,
            'reviewed_by' => $reviewer->getKey(),
            'reviewed_at' => now(),
            'review_comment' => $comment,
        ])->save();
    }
}

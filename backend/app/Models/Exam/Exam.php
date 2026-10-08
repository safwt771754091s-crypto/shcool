<?php

namespace App\Models\Exam;

use App\Models\Academic\ClassSection;
use App\Models\Academic\Subject;
use App\Models\Academic\Term;
use App\Models\User;
use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Exam extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    public const TYPE_DAILY = 'daily';

    public const TYPE_MONTHLY = 'monthly';

    public const TYPE_MIDTERM = 'midterm';

    public const TYPE_FINAL = 'final';

    public const TYPE_QUIZ = 'quiz';

    protected $attributes = [
        'type' => self::TYPE_MONTHLY,
        'max_mark' => 100,
        'pass_mark' => 50,
        'weight' => 1,
        'is_published' => false,
    ];

    protected $fillable = [
        'tenant_id',
        'subject_id',
        'class_section_id',
        'term_id',
        'title',
        'type',
        'held_on',
        'max_mark',
        'pass_mark',
        'weight',
        'is_published',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'held_on' => 'date',
            'max_mark' => 'decimal:2',
            'pass_mark' => 'decimal:2',
            'weight' => 'decimal:2',
            'is_published' => 'boolean',
        ];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class, 'class_section_id');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }
}

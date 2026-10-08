<?php

namespace App\Models\Activities;

use App\Models\User;
use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityAttempt extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'interactive_activity_id',
        'student_id',
        'score',
        'max_score',
        'duration_seconds',
        'answers',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'max_score' => 'decimal:2',
            'duration_seconds' => 'integer',
            'answers' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(InteractiveActivity::class, 'interactive_activity_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function percentage(): float
    {
        return $this->max_score > 0
            ? round(((float) $this->score / (float) $this->max_score) * 100, 2)
            : 0.0;
    }
}

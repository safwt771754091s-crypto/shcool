<?php

namespace App\Models\Activities;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityQuestion extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'interactive_activity_id',
        'prompt_en',
        'prompt_ar',
        'answer_type',
        'options',
        'correct_answer',
        'points',
        'sequence',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'points' => 'decimal:2',
            'sequence' => 'integer',
        ];
    }


    /**
     * Platform-wide rows (tenant_id IS NULL) stay visible to every tenant.
     */
    public static function includesGlobalRows(): bool
    {
        return true;
    }
    public function activity(): BelongsTo
    {
        return $this->belongsTo(InteractiveActivity::class, 'interactive_activity_id');
    }

    public function isCorrect(string $answer): bool
    {
        return mb_strtolower(trim($answer)) === mb_strtolower(trim($this->correct_answer));
    }
}

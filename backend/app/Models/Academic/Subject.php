<?php

namespace App\Models\Academic;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subject extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'stage',
        'pass_mark',
        'max_mark',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'pass_mark' => 'decimal:2',
            'max_mark' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function units(): HasMany
    {
        return $this->hasMany(\App\Models\Teaching\CurriculumUnit::class);
    }
}

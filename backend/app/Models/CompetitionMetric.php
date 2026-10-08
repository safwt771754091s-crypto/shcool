<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompetitionMetric extends Model
{
    use HasFactory;

    public const DIRECTION_HIGHER = 'higher';

    public const DIRECTION_LOWER = 'lower';

    protected $fillable = [
        'key',
        'name',
        'description',
        'direction',
        'weight',
        'scopes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'scopes' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function scores(): HasMany
    {
        return $this->hasMany(CompetitionScore::class);
    }

    public function appliesTo(string $scope): bool
    {
        return $this->scopes === null || in_array($scope, $this->scopes, true);
    }
}

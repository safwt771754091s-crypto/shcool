<?php

namespace App\Models\Support;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An approved knowledge-base article (مقالة في قاعدة المعرفة).
 *
 * Tenant-scoped, but the platform owner may publish global articles
 * (tenant_id NULL) that every school can retrieve. Only published articles are
 * ever surfaced to the assistant.
 */
class KbArticle extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'category',
        'locale',
        'question',
        'answer',
        'keywords',
        'published',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'published' => 'boolean',
            'priority' => 'integer',
        ];
    }

    /** Global (owner-authored) articles stay visible to every tenant. */
    public static function includesGlobalRows(): bool
    {
        return true;
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('published', true);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A node in the administrative hierarchy:
 * ministry -> governorate -> directorate -> school -> branch.
 */
class Organization extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_MINISTRY = 'ministry';

    public const TYPE_GOVERNORATE = 'governorate';

    public const TYPE_DIRECTORATE = 'directorate';

    public const TYPE_SCHOOL = 'school';

    public const TYPE_BRANCH = 'branch';

    /**
     * Ordered hierarchy: type => level.
     *
     * @var array<string, int>
     */
    public const LEVELS = [
        self::TYPE_MINISTRY => 1,
        self::TYPE_GOVERNORATE => 2,
        self::TYPE_DIRECTORATE => 3,
        self::TYPE_SCHOOL => 4,
        self::TYPE_BRANCH => 5,
    ];

    protected $fillable = [
        'parent_id',
        'type',
        'level',
        'name',
        'code',
        'slug',
        'path',
        'depth',
        'tenant_id',
        'settings',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
            'level' => 'integer',
            'depth' => 'integer',
        ];
    }

    // ---------------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------------

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['job_title', 'is_primary'])
            ->withTimestamps();
    }

    // ---------------------------------------------------------------------
    // Hierarchy helpers
    // ---------------------------------------------------------------------

    /**
     * Ancestors from the root (ministry) down to the immediate parent.
     *
     * @return \Illuminate\Support\Collection<int, Organization>
     */
    public function ancestors(): \Illuminate\Support\Collection
    {
        $ids = array_values(array_filter(explode('/', trim($this->path ?? '', '/'))));

        if ($ids === []) {
            return collect();
        }

        return static::query()
            ->whereIn('id', $ids)
            ->orderBy('level')
            ->get();
    }

    /**
     * Every descendant at or below this node, using the materialized path.
     */
    public function descendantsQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return static::query()->where('path', 'like', $this->path.$this->getKey().'/%');
    }

    /**
     * The school that owns this node. For a school it is the node itself; for
     * a branch it is the parent school; for higher levels it is null.
     */
    public function school(): ?Organization
    {
        return match ($this->type) {
            self::TYPE_SCHOOL => $this,
            self::TYPE_BRANCH => $this->parent,
            default => null,
        };
    }

    public function isSchool(): bool
    {
        return $this->type === self::TYPE_SCHOOL;
    }

    public function isBranch(): bool
    {
        return $this->type === self::TYPE_BRANCH;
    }

    /**
     * Recompute the materialized path/depth from the parent. Call before save
     * whenever parent_id changes.
     */
    public function refreshHierarchy(): void
    {
        $this->level = self::LEVELS[$this->type] ?? 4;

        if ($this->parent_id === null) {
            $this->path = '/';
            $this->depth = 0;

            return;
        }

        $parent = $this->parent()->firstOrFail();

        $this->path = $parent->path.$parent->getKey().'/';
        $this->depth = $parent->depth + 1;
        $this->level = $parent->level + 1;

        // A branch inherits its school's tenant; a school is its own tenant.
        if ($parent->isSchool()) {
            $this->tenant_id = $parent->getKey();
        } elseif ($parent->type === self::TYPE_BRANCH) {
            $this->tenant_id = $parent->tenant_id;
        }
    }
}

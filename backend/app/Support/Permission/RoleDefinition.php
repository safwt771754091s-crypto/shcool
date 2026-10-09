<?php

namespace App\Support\Permission;

use App\Models\Organization;

/**
 * Declarative definition of a role: its display name, the hierarchy level it
 * belongs to, whether it is global (not bound to a single school) and the set
 * of permissions it grants.
 */
final class RoleDefinition
{
    /**
     * @param  list<string>  $permissions  Permission names, or ['*'] for all.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $displayName,
        public readonly int $level,
        public readonly array $permissions,
        public readonly bool $global = false,
        public readonly bool $system = true,
    ) {}

    public static function fromLevel(string $type): int
    {
        return Organization::LEVELS[$type] ?? 4;
    }
}

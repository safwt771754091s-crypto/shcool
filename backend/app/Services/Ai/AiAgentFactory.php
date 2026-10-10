<?php

namespace App\Services\Ai;

use App\Models\User;

/**
 * Resolves agent personas from config and decides which ones a user may use.
 */
class AiAgentFactory
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return config('ai.agents', []);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * Agents the given user may talk to.
     *
     * @return list<array{key: string, label: string, description: string}>
     */
    public function forUser(User $user): array
    {
        $agents = [];

        foreach ($this->all() as $key => $agent) {
            if (! $this->allows($key, $user)) {
                continue;
            }

            $agents[] = [
                'key' => $key,
                'label' => $agent['label'] ?? $key,
                'description' => $agent['description'] ?? '',
            ];
        }

        return $agents;
    }

    public function allows(string $key, User $user): bool
    {
        $agent = $this->find($key);

        if ($agent === null) {
            return false;
        }

        if ($user->is_platform_admin) {
            return true;
        }

        $roles = $agent['roles'] ?? [];

        return $user->hasAnyRole($roles);
    }
}

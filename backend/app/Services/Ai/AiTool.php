<?php

namespace App\Services\Ai;

use App\Models\User;

/**
 * A read-only capability an AI agent may invoke.
 *
 * Tools are executed inside an already-initialised tenant context, so the
 * global scopes on the models make isolation automatic: a query can only ever
 * return the current school's rows.
 */
abstract class AiTool
{
    /** Machine name used in the model's tool schema. */
    abstract public function name(): string;

    /** Arabic description shown to the model. */
    abstract public function description(): string;

    /**
     * JSON-schema for the tool arguments.
     *
     * @return array<string, mixed>
     */
    abstract public function parameters(): array;

    /**
     * Run the tool and return a JSON-serialisable result.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    abstract public function handle(array $arguments, User $user): array;

    /**
     * Whether this tool may run for the given user. Defaults to allowed; tools
     * that expose sensitive data override this with a permission check.
     */
    public function allows(User $user): bool
    {
        return true;
    }

    /**
     * OpenAI-style tool definition.
     *
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => $this->description(),
                'parameters' => $this->parameters(),
            ],
        ];
    }
}

<?php

namespace App\Models\Ai;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One message inside an AI conversation (رسالة).
 */
class AiMessage extends Model
{
    use BelongsToTenant, HasFactory;

    public const ROLE_USER = 'user';

    public const ROLE_ASSISTANT = 'assistant';

    public const ROLE_SYSTEM = 'system';

    public const ROLE_TOOL = 'tool';

    protected $fillable = [
        'tenant_id',
        'conversation_id',
        'role',
        'content',
        'tool_calls',
        'tool_results',
        'model',
        'prompt_tokens',
        'completion_tokens',
    ];

    protected function casts(): array
    {
        return [
            'tool_calls' => 'array',
            'tool_results' => 'array',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }
}

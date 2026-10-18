<?php

namespace App\Models\Support;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One turn of an assistant conversation (رسالة في محادثة الدعم).
 *
 * Stores the classification (intent/confidence) and the knowledge passages the
 * answer was grounded in, so every reply is auditable.
 */
class SupportMessage extends Model
{
    use BelongsToTenant, HasFactory;

    public const ROLE_USER = 'user';

    public const ROLE_ASSISTANT = 'assistant';

    public const ROLE_SYSTEM = 'system';

    protected $fillable = [
        'tenant_id',
        'conversation_id',
        'role',
        'content',
        'intent',
        'confidence',
        'sources',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'sources' => 'array',
            'meta' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SupportConversation::class, 'conversation_id');
    }
}

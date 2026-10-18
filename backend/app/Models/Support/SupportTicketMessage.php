<?php

namespace App\Models\Support;

use App\Models\User;
use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A message on a support ticket (رسالة في تذكرة الدعم).
 *
 * `internal` notes are visible to agents only, never to the requester.
 */
class SupportTicketMessage extends Model
{
    use BelongsToTenant, HasFactory;

    public const AUTHOR_USER = 'user';

    public const AUTHOR_AGENT = 'agent';

    public const AUTHOR_SYSTEM = 'system';

    protected $fillable = [
        'tenant_id',
        'ticket_id',
        'user_id',
        'author_type',
        'body',
        'internal',
    ];

    protected function casts(): array
    {
        return [
            'internal' => 'boolean',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

<?php

namespace App\Services\Support;

use App\Models\Support\SupportTicket;
use App\Models\Support\SupportTicketMessage;
use App\Models\User;
use App\Services\Notifications\NotificationService;

/**
 * Creates and works support tickets (تذاكر الدعم).
 *
 * Kept separate from the assistant so the desk can create/answer tickets
 * directly and so notifications stay in one place.
 */
class SupportTicketService
{
    public function __construct(
        protected NotificationService $notifications,
    ) {}

    /**
     * Open a ticket on behalf of a user.
     *
     * @param  array{subject?: string, body?: string, category?: string, priority?: string, conversation_id?: int|null}  $data
     */
    public function open(User $user, array $data): SupportTicket
    {
        $ticket = SupportTicket::create([
            'user_id' => $user->getKey(),
            'conversation_id' => $data['conversation_id'] ?? null,
            'reference' => SupportTicket::newReference(),
            'subject' => $data['subject'] ?? 'طلب دعم',
            'category' => $data['category'] ?? 'general',
            'status' => SupportTicket::STATUS_OPEN,
            'priority' => $data['priority'] ?? 'normal',
            'last_message_at' => now(),
        ]);

        if (filled($data['body'] ?? null)) {
            $this->addMessage($ticket, $user, (string) $data['body'], SupportTicketMessage::AUTHOR_USER);
        }

        return $ticket->refresh();
    }

    public function addMessage(
        SupportTicket $ticket,
        ?User $author,
        string $body,
        string $authorType = SupportTicketMessage::AUTHOR_AGENT,
        bool $internal = false,
    ): SupportTicketMessage {
        $message = SupportTicketMessage::create([
            'ticket_id' => $ticket->getKey(),
            'user_id' => $author?->getKey(),
            'author_type' => $authorType,
            'body' => $body,
            'internal' => $internal,
        ]);

        $ticket->forceFill(['last_message_at' => now()])->save();

        return $message;
    }

    public function assign(SupportTicket $ticket, User $agent): SupportTicket
    {
        $ticket->forceFill([
            'assigned_to' => $agent->getKey(),
            'status' => SupportTicket::STATUS_PENDING,
        ])->save();

        return $ticket->refresh();
    }

    public function resolve(SupportTicket $ticket): SupportTicket
    {
        $ticket->forceFill([
            'status' => SupportTicket::STATUS_RESOLVED,
            'resolved_at' => now(),
        ])->save();

        return $ticket->refresh();
    }

    public function close(SupportTicket $ticket): SupportTicket
    {
        $ticket->forceFill(['status' => SupportTicket::STATUS_CLOSED])->save();

        return $ticket->refresh();
    }

    /**
     * Best-effort acknowledgement to the requester (never blocks the request).
     */
    public function acknowledge(SupportTicket $ticket): void
    {
        try {
            $this->notifications->sendToUser(
                $ticket->requester,
                'support_ticket_opened',
                [
                    'reference' => $ticket->reference,
                    'subject' => $ticket->subject,
                ],
            );
        } catch (\Throwable) {
            // Notifications are optional infrastructure; a missing template or
            // channel must not fail ticket creation.
        }
    }
}

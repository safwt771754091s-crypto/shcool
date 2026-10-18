<?php

namespace App\Http\Controllers\Api\Support;

use App\Http\Controllers\Controller;
use App\Models\Support\SupportTicket;
use App\Models\Support\SupportTicketMessage;
use App\Services\Support\SupportTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Support desk (مكتب الدعم): tickets raised by the assistant or by users.
 *
 * A user sees and replies to their own tickets; staff with `support.manage`
 * see every ticket in their school, assign, reply and resolve.
 */
class SupportTicketController extends Controller
{
    public function __construct(
        protected SupportTicketService $tickets,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(SupportTicket::STATUSES)],
            'priority' => ['sometimes', Rule::in(SupportTicket::PRIORITIES)],
            'assigned_to' => ['sometimes', 'integer'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();
        $manages = $user->can('support.manage');

        $query = SupportTicket::query()->orderByDesc('last_message_at')->orderByDesc('id');

        // A non-manager only ever sees their own tickets.
        if (! $manages) {
            $query->where('user_id', $user->getKey());
        }

        foreach (['status', 'priority', 'assigned_to'] as $field) {
            if (isset($validated[$field])) {
                $query->where($field, $validated[$field]);
            }
        }

        $query->with(['requester:id,name', 'assignee:id,name']);

        return response()->json($query->paginate($validated['per_page'] ?? 25));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:4000'],
            'category' => ['sometimes', 'string', 'max:60'],
            'priority' => ['sometimes', Rule::in(SupportTicket::PRIORITIES)],
            'conversation_id' => ['sometimes', 'integer', 'exists:support_conversations,id'],
        ]);

        $ticket = $this->tickets->open($request->user(), $validated);
        $this->tickets->acknowledge($ticket);

        return response()->json(['data' => $ticket], 201);
    }

    public function show(Request $request, SupportTicket $ticket): JsonResponse
    {
        $this->authorizeView($request, $ticket);

        $manages = $request->user()->can('support.manage');

        $messages = $ticket->messages()
            ->when(! $manages, fn ($q) => $q->where('internal', false))
            ->get(['id', 'user_id', 'author_type', 'body', 'internal', 'created_at']);

        return response()->json([
            'data' => [
                'ticket' => $ticket->only([
                    'id', 'reference', 'subject', 'category', 'status', 'priority',
                    'user_id', 'assigned_to', 'last_message_at', 'resolved_at', 'created_at',
                ]),
                'messages' => $messages,
            ],
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): JsonResponse
    {
        $this->authorizeView($request, $ticket);

        $manages = $request->user()->can('support.manage');

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
            'internal' => ['sometimes', 'boolean'],
        ]);

        // Only staff may post internal notes.
        $internal = $manages && ($validated['internal'] ?? false);

        $message = $this->tickets->addMessage(
            $ticket,
            $request->user(),
            $validated['body'],
            $manages ? SupportTicketMessage::AUTHOR_AGENT : SupportTicketMessage::AUTHOR_USER,
            $internal,
        );

        if ($manages && $ticket->status === SupportTicket::STATUS_OPEN) {
            $ticket->forceFill(['status' => SupportTicket::STATUS_PENDING])->save();
        }

        return response()->json(['data' => $message], 201);
    }

    public function update(Request $request, SupportTicket $ticket): JsonResponse
    {
        // Desk management only.
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(SupportTicket::STATUSES)],
            'priority' => ['sometimes', Rule::in(SupportTicket::PRIORITIES)],
            'assigned_to' => ['sometimes', 'integer', 'exists:users,id'],
        ]);

        if (isset($validated['assigned_to'])) {
            $ticket->forceFill(['assigned_to' => $validated['assigned_to']])->save();
        }

        if (isset($validated['priority'])) {
            $ticket->forceFill(['priority' => $validated['priority']])->save();
        }

        if (isset($validated['status'])) {
            $ticket = match ($validated['status']) {
                SupportTicket::STATUS_RESOLVED => $this->tickets->resolve($ticket),
                SupportTicket::STATUS_CLOSED => $this->tickets->close($ticket),
                default => tap($ticket)->forceFill(['status' => $validated['status']])->save(),
            };
        }

        return response()->json(['data' => $ticket->refresh()]);
    }

    protected function authorizeView(Request $request, SupportTicket $ticket): void
    {
        $user = $request->user();

        if ($user->can('support.manage')) {
            return;
        }

        if ($ticket->user_id !== $user->getKey()) {
            abort(404, 'التذكرة غير موجودة.');
        }
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ai\AiConversation;
use App\Services\Ai\AiAgentFactory;
use App\Services\Ai\AiAgentService;
use App\Services\Ai\AiToolbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * AI agents (وكلاء الذكاء الاصطناعي).
 *
 * Every endpoint is tenant-scoped: conversations and the data tools only ever
 * see the signed-in user's school.
 */
class AiController extends Controller
{
    public function __construct(
        protected AiAgentService $agents,
        protected AiAgentFactory $factory,
        protected AiToolbox $toolbox,
    ) {}

    /**
     * Available agents, provider status, and the current user's conversations.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'available' => $this->agents->isAvailable(),
                'agents' => $this->factory->forUser($user),
                'conversations' => AiConversation::query()
                    ->where('user_id', $user->getKey())
                    ->orderByDesc('last_message_at')
                    ->orderByDesc('id')
                    ->limit(50)
                    ->get(['id', 'agent', 'title', 'last_message_at']),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! $this->factory->allows($validated['agent'], $user)) {
            abort(403, 'هذا الوكيل غير متاح لدورك.');
        }

        $conversation = AiConversation::create([
            'user_id' => $user->getKey(),
            'agent' => $validated['agent'],
            'last_message_at' => now(),
        ]);

        return response()->json(['data' => $conversation], 201);
    }

    public function show(Request $request, AiConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);

        return response()->json([
            'data' => [
                'conversation' => $conversation->only(['id', 'agent', 'title', 'last_message_at']),
                'messages' => $conversation->messages()
                    ->whereIn('role', ['user', 'assistant'])
                    ->get(['id', 'role', 'content', 'tool_results', 'created_at']),
            ],
        ]);
    }

    /**
     * Send a message and get the agent's reply.
     */
    public function message(Request $request, AiConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $result = $this->agents->respond($conversation, $request->user(), $validated['message']);

        return response()->json([
            'data' => [
                'reply' => $result['message']->only(['id', 'role', 'content', 'model', 'created_at']),
                'tools_used' => $result['tools_used'],
                'notice' => $result['notice'],
                'available' => $this->agents->isAvailable(),
            ],
        ]);
    }

    public function destroy(Request $request, AiConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);
        $conversation->delete();

        return response()->json(['message' => 'تم حذف المحادثة.']);
    }

    protected function authorizeConversation(Request $request, AiConversation $conversation): void
    {
        if ($conversation->user_id !== $request->user()->getKey()) {
            abort(404, 'المحادثة غير موجودة.');
        }
    }
}

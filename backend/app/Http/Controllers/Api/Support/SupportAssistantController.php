<?php

namespace App\Http\Controllers\Api\Support;

use App\Http\Controllers\Controller;
use App\Models\Support\SupportConversation;
use App\Support\Tenancy\Scopes\TenantScope;
use App\Support\Tenancy\TenantManager;
use App\Services\Support\SupportAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Customer-service assistant chat (مساعد خدمة العملاء).
 *
 * Every endpoint is scoped to the signed-in user: a user only ever sees their
 * own conversations, and each reply is grounded in the approved knowledge base.
 */
class SupportAssistantController extends Controller
{
    public function __construct(
        protected SupportAssistantService $assistant,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'available' => $this->assistant->isAvailable(),
                'intents' => collect(config('support.intents', []))
                    ->map(fn ($i, $key) => ['key' => $key, 'label' => $i['label'] ?? $key])
                    ->values(),
                'conversations' => SupportConversation::query()
                    ->where('user_id', $user->getKey())
                    ->orderByDesc('last_message_at')
                    ->orderByDesc('id')
                    ->limit(50)
                    ->get(['id', 'title', 'intent', 'last_message_at']),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:120'],
        ]);

        $conversation = SupportConversation::create([
            'user_id' => $request->user()->getKey(),
            'title' => $validated['title'] ?? null,
            'locale' => $this->locale($request),
            'last_message_at' => now(),
        ]);

        return response()->json(['data' => $conversation], 201);
    }

    public function show(Request $request, int $conversation): JsonResponse
    {
        $conversation = $this->findConversation($request, $conversation);

        return response()->json([
            'data' => [
                'conversation' => $conversation->only(['id', 'title', 'intent', 'last_message_at']),
                'messages' => $conversation->messages()
                    ->withoutGlobalScope(TenantScope::class)
                    ->whereIn('role', ['user', 'assistant'])
                    ->get(['id', 'role', 'content', 'intent', 'confidence', 'sources', 'meta', 'created_at']),
            ],
        ]);
    }

    public function message(Request $request, int $conversation): JsonResponse
    {
        $conversation = $this->findConversation($request, $conversation);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $result = $this->assistant->respond($conversation, $request->user(), $validated['message']);

        return response()->json([
            'data' => [
                'reply' => $result['reply']->only(['id', 'role', 'content', 'intent', 'confidence', 'sources', 'meta', 'created_at']),
                'intent' => $result['intent'],
                'confidence' => $result['confidence'],
                'sources' => $result['sources'],
                'escalate' => $result['escalate'],
                'ticket' => $result['ticket']?->only(['id', 'reference', 'status']),
                'available' => $this->assistant->isAvailable(),
            ],
        ]);
    }

    public function destroy(Request $request, int $conversation): JsonResponse
    {
        $conversation = $this->findConversation($request, $conversation);
        $conversation->delete();

        return response()->json(['message' => 'تم حذف المحادثة.']);
    }

    /**
     * Resolve a conversation the caller owns.
     *
     * Ownership is the authorisation rule, but the lookup stays tenant-aware:
     * a tenant user can never reach another school's row. A platform-level user
     * (owner/ministry) has no active tenant, so the scoped binding would 404 a
     * conversation they legitimately created — hence the explicit bypass.
     */
    protected function findConversation(Request $request, int $id): SupportConversation
    {
        $user = $request->user();
        $tenantId = app(TenantManager::class)->tenantId();

        $query = $tenantId === null
            ? SupportConversation::allTenants()
            : SupportConversation::query();

        $conversation = $query->find($id);

        if ($conversation === null || $conversation->user_id !== $user->getKey()) {
            abort(404, 'المحادثة غير موجودة.');
        }

        return $conversation;
    }

    protected function locale(Request $request): string
    {
        return str_starts_with((string) $request->header('Accept-Language'), 'en') ? 'en' : 'ar';
    }
}

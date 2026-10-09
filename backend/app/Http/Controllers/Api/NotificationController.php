<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification\NotificationLog;
use App\Models\Notification\NotificationPreference;
use App\Models\Notification\NotificationTemplate;
use App\Models\Student\Guardian;
use App\Models\User;
use App\Services\Notifications\NotificationChannelManager;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Notification management: templates, channel status, sending, logs and the
 * signed-in user's own preferences.
 */
class NotificationController extends Controller
{
    public function __construct(
        protected NotificationService $notifications,
        protected NotificationChannelManager $channels,
    ) {}

    /**
     * Which channels are configured and can deliver right now.
     */
    public function channels(): JsonResponse
    {
        return response()->json([
            'data' => [
                'registered' => $this->channels->names(),
                'available' => $this->channels->available(),
            ],
        ]);
    }

    public function templates(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel' => ['sometimes', Rule::in(NotificationTemplate::CHANNELS)],
            'key' => ['sometimes', 'string'],
        ]);

        $query = NotificationTemplate::query()->orderBy('key');

        if (isset($validated['channel'])) {
            $query->where('channel', $validated['channel']);
        }

        if (isset($validated['key'])) {
            $query->where('key', $validated['key']);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:120'],
            'channel' => ['required', Rule::in(NotificationTemplate::CHANNELS)],
            'locale' => ['sometimes', 'string', 'max:8'],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $template = NotificationTemplate::updateOrCreate(
            [
                'key' => $validated['key'],
                'channel' => $validated['channel'],
                'locale' => $validated['locale'] ?? 'ar',
            ],
            [
                'title' => $validated['title'] ?? null,
                'body' => $validated['body'],
                'is_active' => $validated['is_active'] ?? true,
            ],
        );

        return response()->json([
            'data' => $template,
            'message' => 'تم حفظ القالب.',
        ], 201);
    }

    /**
     * Send an ad-hoc notification to a user or a raw phone number.
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:120'],
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'guardian_id' => ['sometimes', 'integer', 'exists:guardians,id'],
            'phone' => ['sometimes', 'string', 'max:40'],
            'channels' => ['sometimes', 'array'],
            'channels.*' => [Rule::in(NotificationTemplate::CHANNELS)],
            'data' => ['sometimes', 'array'],
            'meta' => ['sometimes', 'array'],
        ]);

        if (! isset($validated['user_id'], $validated['guardian_id'], $validated['phone'])) {
            return response()->json([
                'message' => 'حدّد مستخدماً أو ولي أمر أو رقم هاتف.',
            ], 422);
        }

        $channels = $validated['channels'] ?? null;
        $data = $validated['data'] ?? [];
        $meta = $validated['meta'] ?? [];

        $logs = [];

        if (isset($validated['user_id'])) {
            $user = User::query()->findOrFail($validated['user_id']);
            $logs = $this->notifications->sendToUser($user, $validated['key'], $data, $channels, $meta);
        } elseif (isset($validated['guardian_id'])) {
            $guardian = Guardian::query()->findOrFail($validated['guardian_id']);
            $phone = $guardian->phone ?? $guardian->user?->phone;

            if ($phone === null) {
                return response()->json(['message' => 'لا يوجد رقم هاتف لولي الأمر.'], 422);
            }

            $logs = $this->notifications->sendToPhone($phone, $validated['key'], $data, $channels, $meta);
        } else {
            $logs = $this->notifications->sendToPhone($validated['phone'], $validated['key'], $data, $channels, $meta);
        }

        return response()->json([
            'data' => $logs,
            'message' => 'تمت جدولة الإشعار.',
        ], 202);
    }

    public function logs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel' => ['sometimes', Rule::in(NotificationTemplate::CHANNELS)],
            'status' => ['sometimes', Rule::in([
                NotificationLog::STATUS_QUEUED,
                NotificationLog::STATUS_SENDING,
                NotificationLog::STATUS_SENT,
                NotificationLog::STATUS_FAILED,
            ])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = NotificationLog::query()->orderByDesc('id');

        foreach (['channel', 'status'] as $field) {
            if (isset($validated[$field])) {
                $query->where($field, $validated[$field]);
            }
        }

        return response()->json($query->paginate($validated['per_page'] ?? 25));
    }

    /**
     * The signed-in user's channel preferences.
     */
    public function preferences(Request $request): JsonResponse
    {
        $user = $request->user();

        $preferences = NotificationPreference::query()
            ->where('user_id', $user->getKey())
            ->get()
            ->keyBy('channel');

        $data = array_map(fn (string $channel) => [
            'channel' => $channel,
            'is_enabled' => (bool) ($preferences[$channel]->is_enabled ?? true),
        ], NotificationTemplate::CHANNELS);

        return response()->json(['data' => array_values($data)]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*.channel' => ['required', Rule::in(NotificationTemplate::CHANNELS)],
            'preferences.*.is_enabled' => ['required', 'boolean'],
        ]);

        foreach ($validated['preferences'] as $preference) {
            NotificationPreference::updateOrCreate(
                [
                    'user_id' => $request->user()->getKey(),
                    'channel' => $preference['channel'],
                ],
                ['is_enabled' => $preference['is_enabled']],
            );
        }

        return response()->json(['message' => 'تم تحديث التفضيلات.']);
    }

    /**
     * The signed-in user's in-app notifications.
     */
    public function inbox(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => $user->notifications()->latest()->limit(50)->get(),
            'unread' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['message' => 'تم تعليم الإشعار كمقروء.']);
    }
}

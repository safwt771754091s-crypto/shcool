<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Sync\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Offline sync endpoint (العمل دون إنترنت).
 */
class SyncController extends Controller
{
    public function __construct(protected SyncService $sync) {}

    /**
     * Replay a batch of offline mutations. Idempotent per client_batch_id.
     */
    public function push(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_batch_id' => ['required', 'uuid'],
            'device_id' => ['nullable', 'string', 'max:80'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.type' => ['required', 'string', 'in:attendance.register,grade.record'],
            'items.*.client_id' => ['nullable', 'string', 'max:100'],
            'items.*.payload' => ['required', 'array'],
        ]);

        $batch = $this->sync->apply(
            $request->user(),
            $validated['client_batch_id'],
            $validated['items'],
            $validated['device_id'] ?? null,
        );

        return response()->json([
            'data' => $batch,
            'message' => 'تمت مزامنة التغييرات.',
        ], 201);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LeaderboardEntryResource;
use App\Models\LeaderboardEntry;
use App\Models\RankingPeriod;
use App\Services\Competition\CompetitionService;
use App\Support\Tenancy\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Exposes the competition leaderboards.
 *
 * Visibility: a school sees its own students/classes and the school's position
 * among schools; higher levels see the wider boards. Platform admins see all.
 */
class LeaderboardController extends Controller
{
    public function __construct(
        protected CompetitionService $competition,
        protected TenantManager $tenants,
    ) {
    }

    public function index(Request $request, RankingPeriod $period): JsonResponse
    {
        $validated = $request->validate([
            'scope' => ['required', Rule::in(LeaderboardEntry::SCOPES)],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $entries = $this->competition->leaderboard(
            $period,
            $validated['scope'],
            $validated['limit'] ?? null,
        );

        return response()->json([
            'data' => LeaderboardEntryResource::collection($entries),
            'meta' => [
                'scope' => $validated['scope'],
                'period' => $period->only(['id', 'name', 'starts_on', 'ends_on']),
            ],
        ]);
    }

    /**
     * The current school's own position in each scope for a period.
     */
    public function myPosition(Request $request, RankingPeriod $period): JsonResponse
    {
        $tenantId = $this->tenants->tenantId();

        $entry = LeaderboardEntry::query()
            ->where('ranking_period_id', $period->getKey())
            ->where('scope_type', LeaderboardEntry::SCOPE_SCHOOL)
            ->where('scope_id', $tenantId)
            ->first();

        return response()->json([
            'data' => $entry ? new LeaderboardEntryResource($entry) : null,
        ]);
    }

    /**
     * Recompute the boards for a period. Restricted to staff.
     */
    public function recompute(RankingPeriod $period): JsonResponse
    {
        $this->competition->recompute($period);

        return response()->json(['message' => 'تم إعادة احتساب الترتيب.']);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LeaderboardEntryResource;
use App\Models\LeaderboardEntry;
use App\Models\RankingPeriod;
use App\Models\Student\Student;
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
    ) {}

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
     * List ranking periods, most recent first (optionally only active ones).
     */
    public function periods(Request $request): JsonResponse
    {
        $periods = RankingPeriod::query()
            ->when($request->boolean('active_only'), fn ($q) => $q->where('is_active', true))
            ->orderByDesc('starts_on')
            ->get();

        return response()->json([
            'data' => $periods->map(fn (RankingPeriod $p) => [
                'id' => $p->getKey(),
                'name' => $p->name,
                'type' => $p->type,
                'starts_on' => $p->starts_on?->toDateString(),
                'ends_on' => $p->ends_on?->toDateString(),
                'is_active' => $p->is_active,
                'is_locked' => $p->is_locked,
            ]),
        ]);
    }

    /**
     * Create a ranking period (staff only).
     */
    public function storePeriod(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['nullable', Rule::in([
                RankingPeriod::TYPE_TERM,
                RankingPeriod::TYPE_SEMESTER,
                RankingPeriod::TYPE_MONTH,
                RankingPeriod::TYPE_YEAR,
                RankingPeriod::TYPE_CUSTOM,
            ])],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $period = RankingPeriod::create($validated + [
            'type' => $validated['type'] ?? RankingPeriod::TYPE_TERM,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'data' => [
                'id' => $period->getKey(),
                'name' => $period->name,
                'type' => $period->type,
                'starts_on' => $period->starts_on?->toDateString(),
                'ends_on' => $period->ends_on?->toDateString(),
                'is_active' => $period->is_active,
            ],
        ], 201);
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

    /**
     * Recompute the boards from real academic data (grades, attendance,
     * activities) then roll them up the hierarchy.
     */
    public function recomputeFromAcademics(RankingPeriod $period): JsonResponse
    {
        $this->competition->recomputeFromAcademics($period);

        return response()->json(['message' => 'تم إعادة احتساب الترتيب من البيانات الأكاديمية.']);
    }

    /**
     * The signed-in student's own position in each scope of the period.
     */
    public function myStudentPosition(Request $request, RankingPeriod $period): JsonResponse
    {
        $studentId = $request->integer('student_id') ?: Student::query()
            ->where('user_id', $request->user()->getKey())
            ->value('id');

        if ($studentId === null) {
            return response()->json(['data' => null]);
        }

        $entry = LeaderboardEntry::query()
            ->where('ranking_period_id', $period->getKey())
            ->where('scope_type', LeaderboardEntry::SCOPE_STUDENT)
            ->where('scope_id', $studentId)
            ->first();

        return response()->json([
            'data' => $entry ? new LeaderboardEntryResource($entry) : null,
        ]);
    }

    /**
     * The classes of the current school ranked for the period.
     */
    public function classLeaderboard(Request $request, RankingPeriod $period): JsonResponse
    {
        $entries = LeaderboardEntry::query()
            ->where('ranking_period_id', $period->getKey())
            ->where('scope_type', LeaderboardEntry::SCOPE_CLASS)
            ->orderBy('rank')
            ->when($request->integer('limit'), fn ($q, $limit) => $q->limit($limit))
            ->get();

        return response()->json([
            'data' => LeaderboardEntryResource::collection($entries),
            'meta' => ['scope' => LeaderboardEntry::SCOPE_CLASS],
        ]);
    }
}

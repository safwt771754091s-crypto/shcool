<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sports\SportCompetition;
use App\Models\Sports\SportMatch;
use App\Services\Sports\SportsLeagueService;
use App\Support\Competition\CompetitionScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Sports league (الدوري الرياضي): registration, brackets and results.
 */
class SportsLeagueController extends Controller
{
    public function __construct(protected SportsLeagueService $league)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = SportCompetition::query()->orderByDesc('id');

        if ($sport = $request->string('sport')->toString()) {
            $query->where('sport', $sport);
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sport' => ['required', 'string', 'max:40'],
            'stage' => ['sometimes', 'string', 'max:20'],
            'age_group' => ['nullable', 'string', 'max:20'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date'],
        ]);

        $competition = $this->league->createCompetition(array_merge($validated, [
            'organizer_id' => $request->user()->getKey(),
        ]));

        return response()->json([
            'data' => $competition,
            'message' => 'تم إنشاء الدوري.',
        ], 201);
    }

    public function show(SportCompetition $competition): JsonResponse
    {
        $competition->load([
            'participants',
            'matches' => fn ($q) => $q->orderBy('scope_type')->orderBy('round_number')->orderBy('bracket_slot'),
        ]);

        return response()->json(['data' => $competition]);
    }

    /**
     * Register a team at a rung of the ladder.
     */
    public function registerParticipant(Request $request, SportCompetition $competition): JsonResponse
    {
        $validated = $request->validate([
            'scope_type' => ['required', Rule::in(CompetitionScope::LADDER)],
            'scope_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'coach' => ['nullable', 'string', 'max:120'],
            'seed' => ['nullable', 'integer', 'min:1'],
        ]);

        $participant = $this->league->registerParticipant(
            $competition,
            $validated['scope_type'],
            $validated,
        );

        return response()->json([
            'data' => $participant,
            'message' => 'تم تسجيل الفريق.',
        ], 201);
    }

    /**
     * Generate the elimination bracket for a rung (defaults to current scope).
     */
    public function generateBracket(Request $request, SportCompetition $competition): JsonResponse
    {
        $validated = $request->validate([
            'scope_type' => ['sometimes', Rule::in(CompetitionScope::LADDER)],
        ]);

        $scope = $validated['scope_type'] ?? $competition->current_scope;

        $matches = $this->league->generateBracket($competition, $scope);

        return response()->json([
            'data' => $matches->values(),
            'message' => 'تم توليد جدول التصفيات.',
        ], 201);
    }

    /**
     * Record a match result and advance the winner.
     */
    public function recordResult(Request $request, SportMatch $match): JsonResponse
    {
        $validated = $request->validate([
            'home_score' => ['required', 'integer', 'min:0'],
            'away_score' => ['required', 'integer', 'min:0'],
            'venue' => ['nullable', 'string', 'max:120'],
        ]);

        $match = $this->league->recordResult(
            $match,
            (int) $validated['home_score'],
            (int) $validated['away_score'],
            $validated['venue'] ?? null,
        );

        return response()->json([
            'data' => $match,
            'message' => 'تم تسجيل نتيجة المباراة.',
        ]);
    }

    /**
     * Promote the winners of the current rung to the next rung.
     */
    public function advance(Request $request, SportCompetition $competition): JsonResponse
    {
        $matches = $this->league->promoteWinners($competition);

        return response()->json([
            'data' => [
                'current_scope' => $competition->refresh()->current_scope,
                'status' => $competition->status,
                'matches' => $matches->values(),
            ],
            'message' => 'تم ترقية الفائزين إلى المرحلة التالية.',
        ]);
    }
}

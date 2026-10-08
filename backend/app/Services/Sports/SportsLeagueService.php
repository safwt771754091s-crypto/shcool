<?php

namespace App\Services\Sports;

use App\Models\Sports\SportCompetition;
use App\Models\Sports\SportMatch;
use App\Models\Sports\SportParticipant;
use App\Support\Competition\CompetitionScope;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Runs a single-elimination tournament that climbs the competition ladder:
 *
 *   sections -> classes -> schools -> directorates -> governorates -> ministry
 *
 * At each rung a bracket is generated between that rung's participants. The
 * winners are then promoted to the next rung and a fresh bracket is built.
 */
class SportsLeagueService
{
    /**
     * Create a competition. `tenant_id` is stamped automatically by the tenant
     * scope when one is active; a null tenant means a platform-wide cup.
     */
    public function createCompetition(array $attributes): SportCompetition
    {
        return SportCompetition::create(array_merge([
            'current_scope' => CompetitionScope::SECTION,
            'status' => SportCompetition::STATUS_DRAFT,
        ], $attributes));
    }

    /**
     * @param  array{scope_id?: int|null, name: string, coach?: string|null, seed?: int|null}  $data
     */
    public function registerParticipant(
        SportCompetition $competition,
        string $scopeType,
        array $data,
    ): SportParticipant {
        // Identity is the linked entity when there is one, otherwise the team
        // name. Matching on a NULL scope_id alone would collapse every
        // name-only entry onto a single row (NULL = NULL is not true in SQL).
        $keys = [
            'sport_competition_id' => $competition->getKey(),
            'scope_type' => $scopeType,
        ];

        if (! empty($data['scope_id'])) {
            $keys['scope_id'] = $data['scope_id'];
        } else {
            $keys['scope_id'] = null;
            $keys['name'] = $data['name'];
        }

        return SportParticipant::updateOrCreate($keys, [
            'name' => $data['name'],
            'coach' => $data['coach'] ?? null,
            'seed' => $data['seed'] ?? null,
        ]);
    }

    /**
     * Build the elimination bracket for one rung.
     *
     * Participants are padded to the next power of two; missing opponents are
     * byes and the present side advances automatically.
     *
     * @return Collection<int, SportMatch>
     */
    public function generateBracket(SportCompetition $competition, string $scopeType): Collection
    {
        $participants = $competition->participants()
            ->where('scope_type', $scopeType)
            ->orderByRaw('seed is null, seed asc')
            ->orderBy('id')
            ->get();

        if ($participants->count() < 2) {
            return collect();
        }

        return DB::transaction(function () use ($competition, $scopeType, $participants) {
            $slots = $this->paddedSlots($participants);
            $size = count($slots);
            $rounds = (int) round(log($size, 2));

            $byRound = $this->createMatchShells($competition, $scopeType, $rounds, $size);
            $this->wireBracket($byRound);
            $this->fillFirstRound($byRound[1], $slots);

            $competition->forceFill([
                'current_scope' => $scopeType,
                'status' => SportCompetition::STATUS_KNOCKOUT,
            ])->save();

            return collect($byRound)->flatten();
        });
    }

    /**
     * Record a result and push the winner into the next match.
     */
    public function recordResult(
        SportMatch $match,
        int $homeScore,
        int $awayScore,
        ?string $venue = null,
    ): SportMatch {
        return DB::transaction(function () use ($match, $homeScore, $awayScore, $venue) {
            $match->forceFill([
                'home_score' => $homeScore,
                'away_score' => $awayScore,
                'venue' => $venue,
                'status' => SportMatch::STATUS_PLAYED,
                'played_at' => now(),
            ])->save();

            $winner = $match->decideWinner();

            if ($winner !== null) {
                $match->forceFill(['winner_participant_id' => $winner->getKey()])->save();
                $this->advanceWinner($match->refresh(), $winner);
            }

            return $match->refresh();
        });
    }

    /**
     * The winner of the final match played at a given rung.
     */
    public function scopeWinner(SportCompetition $competition, string $scopeType): ?SportParticipant
    {
        $lastRound = $competition->matches()
            ->where('scope_type', $scopeType)
            ->max('round_number');

        if ($lastRound === null) {
            return null;
        }

        return $competition->matches()
            ->where('scope_type', $scopeType)
            ->where('round_number', $lastRound)
            ->whereNotNull('winner_participant_id')
            ->first()?->winner;
    }

    /**
     * Promote the winners of the current rung to the next rung and build its
     * bracket. A custom `$map` decides the identity of the promoted entry
     * (e.g. a section champion represents its parent class).
     *
     * @param  (Closure(SportParticipant): array{scope_id?: int|null, name: string, coach?: string|null, seed?: int|null})|null  $map
     * @return Collection<int, SportMatch>
     */
    public function promoteWinners(SportCompetition $competition, ?Closure $map = null): Collection
    {
        $currentScope = $competition->current_scope;
        $nextScope = CompetitionScope::next($currentScope);

        if ($nextScope === null) {
            $competition->forceFill(['status' => SportCompetition::STATUS_FINISHED])->save();

            return collect();
        }

        $winners = $this->winnersAt($competition, $currentScope);

        if ($winners->isEmpty()) {
            return collect();
        }

        foreach ($winners as $winner) {
            $data = $map !== null
                ? $map($winner)
                : ['scope_id' => $winner->scope_id, 'name' => $winner->name, 'coach' => $winner->coach];

            $this->registerParticipant($competition, $nextScope, $data);
        }

        $competition->forceFill(['current_scope' => $nextScope])->save();

        return $this->generateBracket($competition->refresh(), $nextScope);
    }

    /**
     * Winners of the last round at a rung (all finalists that won a match).
     *
     * @return Collection<int, SportParticipant>
     */
    public function winnersAt(SportCompetition $competition, string $scopeType): Collection
    {
        $lastRound = $competition->matches()
            ->where('scope_type', $scopeType)
            ->max('round_number');

        if ($lastRound === null) {
            return collect();
        }

        return $competition->matches()
            ->where('scope_type', $scopeType)
            ->where('round_number', $lastRound)
            ->with('winner')
            ->get()
            ->pluck('winner')
            ->filter()
            ->unique('id')
            ->values();
    }

    // -----------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------

    /**
     * @param  Collection<int, SportParticipant>  $participants
     * @return list<SportParticipant|null>
     */
    protected function paddedSlots(Collection $participants): array
    {
        $slots = $participants->all();
        $size = 1;

        while ($size < count($slots)) {
            $size *= 2;
        }

        while (count($slots) < $size) {
            $slots[] = null;
        }

        return $slots;
    }

    /**
     * @return array<int, list<SportMatch>>
     */
    protected function createMatchShells(SportCompetition $competition, string $scopeType, int $rounds, int $size): array
    {
        $byRound = [];
        $count = intdiv($size, 2);

        for ($round = 1; $round <= $rounds; $round++) {
            $byRound[$round] = [];

            for ($slot = 0; $slot < $count; $slot++) {
                $byRound[$round][] = SportMatch::create([
                    'sport_competition_id' => $competition->getKey(),
                    'scope_type' => $scopeType,
                    'round_number' => $round,
                    'bracket_slot' => $slot,
                    'status' => SportMatch::STATUS_SCHEDULED,
                ]);
            }

            $count = intdiv($count, 2);
        }

        return $byRound;
    }

    /**
     * @param  array<int, list<SportMatch>>  $byRound
     */
    protected function wireBracket(array $byRound): void
    {
        $rounds = count($byRound);

        for ($round = 1; $round < $rounds; $round++) {
            foreach ($byRound[$round] as $slot => $match) {
                $next = $byRound[$round + 1][intdiv($slot, 2)];

                $match->forceFill(['next_match_id' => $next->getKey()])->save();
            }
        }
    }

    /**
     * @param  list<SportMatch>  $matches
     * @param  list<SportParticipant|null>  $slots
     */
    protected function fillFirstRound(array $matches, array $slots): void
    {
        foreach ($matches as $index => $match) {
            $home = $slots[$index * 2] ?? null;
            $away = $slots[$index * 2 + 1] ?? null;

            $match->forceFill([
                'home_participant_id' => $home?->getKey(),
                'away_participant_id' => $away?->getKey(),
            ])->save();

            // A lone side gets a bye and advances without playing.
            if ($home !== null && $away === null) {
                $this->grantBye($match, $home);
            } elseif ($away !== null && $home === null) {
                $this->grantBye($match, $away);
            }
        }
    }

    protected function grantBye(SportMatch $match, SportParticipant $participant): void
    {
        $match->forceFill([
            'winner_participant_id' => $participant->getKey(),
            'status' => SportMatch::STATUS_WALKOVER,
            'played_at' => now(),
        ])->save();

        $this->advanceWinner($match->refresh(), $participant);
    }

    protected function advanceWinner(SportMatch $match, SportParticipant $winner): void
    {
        if ($match->next_match_id === null) {
            return;
        }

        $next = SportMatch::find($match->next_match_id);

        if ($next === null) {
            return;
        }

        $field = ((int) $match->bracket_slot % 2 === 0)
            ? 'home_participant_id'
            : 'away_participant_id';

        $next->forceFill([$field => $winner->getKey()])->save();
    }
}

<?php

namespace Tests\Feature;

use App\Models\Sports\SportCompetition;
use App\Models\Sports\SportMatch;
use App\Services\Sports\SportsLeagueService;
use App\Support\Competition\CompetitionScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SportsLeagueTest extends TestCase
{
    use RefreshDatabase;

    protected function service(): SportsLeagueService
    {
        return app(SportsLeagueService::class);
    }

    protected function competition(): SportCompetition
    {
        return $this->service()->createCompetition([
            'name' => 'دوري كرة القدم',
            'sport' => 'football',
            'stage' => 'primary',
        ]);
    }

    public function test_bracket_is_generated_for_four_teams(): void
    {
        $competition = $this->competition();

        foreach (['أ', 'ب', 'ج', 'د'] as $name) {
            $this->service()->registerParticipant($competition, CompetitionScope::SECTION, ['name' => $name]);
        }

        $matches = $this->service()->generateBracket($competition, CompetitionScope::SECTION);

        // 4 teams -> 3 matches across 2 rounds.
        $this->assertCount(3, $matches);
        $this->assertSame(2, $matches->where('round_number', 1)->count());
        $this->assertSame(1, $matches->where('round_number', 2)->count());
        $this->assertSame(SportCompetition::STATUS_KNOCKOUT, $competition->refresh()->status);
    }

    public function test_odd_team_count_grants_a_bye(): void
    {
        $competition = $this->competition();

        foreach (['أ', 'ب', 'ج'] as $name) {
            $this->service()->registerParticipant($competition, CompetitionScope::SECTION, ['name' => $name]);
        }

        $matches = $this->service()->generateBracket($competition, CompetitionScope::SECTION);

        // 3 teams -> padded to 4 -> one bye.
        $byes = $matches->where('status', SportMatch::STATUS_WALKOVER);
        $this->assertCount(1, $byes);
        $this->assertNotNull($byes->first()->winner_participant_id);
    }

    public function test_result_advances_winner_to_next_round(): void
    {
        $competition = $this->competition();

        foreach (['أ', 'ب', 'ج', 'د'] as $name) {
            $this->service()->registerParticipant($competition, CompetitionScope::SECTION, ['name' => $name]);
        }

        $this->service()->generateBracket($competition, CompetitionScope::SECTION);

        $semi = SportMatch::query()
            ->where('sport_competition_id', $competition->id)
            ->where('round_number', 1)
            ->orderBy('bracket_slot')
            ->firstOrFail();

        $this->service()->recordResult($semi, 3, 1);

        $semi->refresh();
        $this->assertSame(SportMatch::STATUS_PLAYED, $semi->status);
        $this->assertNotNull($semi->winner_participant_id);

        $final = SportMatch::query()
            ->where('sport_competition_id', $competition->id)
            ->where('round_number', 2)
            ->firstOrFail();

        // Slot 0 winner fills the home side of the final.
        $this->assertSame($semi->winner_participant_id, $final->home_participant_id);
    }

    public function test_winners_are_promoted_up_the_ladder(): void
    {
        $competition = $this->competition();

        foreach (['أ', 'ب'] as $name) {
            $this->service()->registerParticipant($competition, CompetitionScope::SECTION, ['name' => $name]);
        }

        $this->service()->generateBracket($competition, CompetitionScope::SECTION);

        $match = SportMatch::query()->where('sport_competition_id', $competition->id)->firstOrFail();
        $this->service()->recordResult($match, 2, 0);

        $this->service()->promoteWinners($competition);

        $competition->refresh();
        $this->assertSame(CompetitionScope::CLASS_LEVEL, $competition->current_scope);

        // The section winner now plays at the class rung.
        $this->assertSame(
            1,
            $competition->participants()->where('scope_type', CompetitionScope::CLASS_LEVEL)->count()
        );
    }

    public function test_ladder_climbs_to_the_ministry_final(): void
    {
        $this->assertSame(CompetitionScope::CLASS_LEVEL, CompetitionScope::next(CompetitionScope::SECTION));
        $this->assertSame(CompetitionScope::SCHOOL, CompetitionScope::next(CompetitionScope::CLASS_LEVEL));
        $this->assertSame(CompetitionScope::DIRECTORATE, CompetitionScope::next(CompetitionScope::SCHOOL));
        $this->assertSame(CompetitionScope::GOVERNORATE, CompetitionScope::next(CompetitionScope::DIRECTORATE));
        $this->assertSame(CompetitionScope::MINISTRY, CompetitionScope::next(CompetitionScope::GOVERNORATE));
        $this->assertNull(CompetitionScope::next(CompetitionScope::MINISTRY));
        $this->assertTrue(CompetitionScope::isFinal(CompetitionScope::MINISTRY));
    }
}

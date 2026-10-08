<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Sports\SportCompetition;
use App\Models\Sports\SportMatch;
use App\Services\Sports\SportsLeagueService;
use App\Support\Competition\CompetitionScope;
use App\Support\Tenancy\TenantManager;
use Illuminate\Database\Seeder;

/**
 * Seeds a sample primary-school football league for the sample school and
 * plays its first two rounds so the bracket, the promotion and the winner
 * logic are all visible immediately.
 */
class SportsLeagueSeeder extends Seeder
{
    public function run(SportsLeagueService $league, TenantManager $tenants): void
    {
        $school = Organization::query()->where('type', Organization::TYPE_SCHOOL)->first();

        if (! $school) {
            return;
        }

        $tenants->setTenant($school);

        $competition = $league->createCompetition([
            'name' => 'دوري كرة القدم الابتدائي',
            'sport' => 'football',
            'stage' => 'primary',
            'age_group' => '10-12',
            'starts_on' => now()->toDateString(),
        ]);

        foreach (['شعبة أ', 'شعبة ب', 'شعبة ج', 'شعبة د'] as $index => $name) {
            $league->registerParticipant($competition, CompetitionScope::SECTION, [
                'name' => $name,
                'seed' => $index + 1,
            ]);
        }

        $league->generateBracket($competition, CompetitionScope::SECTION);

        // Play the semi-finals (2 matches), then the final.
        foreach ([1, 2] as $round) {
            $matches = SportMatch::query()
                ->where('sport_competition_id', $competition->getKey())
                ->where('round_number', $round)
                ->orderBy('bracket_slot')
                ->get();

            foreach ($matches as $match) {
                if ($match->home_participant_id === null || $match->away_participant_id === null) {
                    continue;
                }

                $league->recordResult($match, random_int(1, 4), random_int(0, 3));
            }
        }

        $this->command?->info('Sample sports league seeded and first rounds played.');
    }
}

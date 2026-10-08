<?php

namespace Tests\Feature;

use App\Models\Activities\InteractiveActivity;
use App\Models\Organization;
use App\Models\User;
use App\Services\Activities\ActivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InteractiveActivityTest extends TestCase
{
    use RefreshDatabase;

    protected function activity(Organization $school): InteractiveActivity
    {
        $this->actingAsTenant($school);

        $activity = InteractiveActivity::create([
            'title' => 'English Basics',
            'subject_area' => InteractiveActivity::AREA_ENGLISH,
            'kind' => 'quiz',
            'grade' => 1,
            'is_active' => true,
        ]);

        $activity->questions()->create([
            'prompt_en' => 'What colour is the sky?',
            'prompt_ar' => 'ما لون السماء؟',
            'answer_type' => 'choice',
            'options' => ['blue', 'red', 'green'],
            'correct_answer' => 'blue',
            'points' => 2,
            'sequence' => 1,
        ]);

        $activity->questions()->create([
            'prompt_en' => 'How many legs does a cat have?',
            'prompt_ar' => 'كم عدد أرجل القط؟',
            'answer_type' => 'number',
            'correct_answer' => '4',
            'points' => 3,
            'sequence' => 2,
        ]);

        return $activity;
    }

    public function test_attempts_are_scored_server_side(): void
    {
        $school = Organization::factory()->tenant()->create();
        $activity = $this->activity($school);
        $student = User::factory()->create(['tenant_id' => $school->id]);

        $questions = $activity->questions()->get();

        $attempt = app(ActivityService::class)->submitAttempt($activity, $student, [
            ['question_id' => $questions[0]->id, 'answer' => 'Blue'],
            ['question_id' => $questions[1]->id, 'answer' => '3'],
        ], durationSeconds: 42);

        // First answer is correct (case-insensitive), second is wrong.
        $this->assertEquals(2, (float) $attempt->score);
        $this->assertEquals(5, (float) $attempt->max_score);
        $this->assertSame(40.0, $attempt->percentage());
        $this->assertSame($school->id, $attempt->tenant_id);
    }

    public function test_best_percentage_returns_the_highest_attempt(): void
    {
        $school = Organization::factory()->tenant()->create();
        $activity = $this->activity($school);
        $student = User::factory()->create(['tenant_id' => $school->id]);
        $questions = $activity->questions()->get();

        app(ActivityService::class)->submitAttempt($activity, $student, [
            ['question_id' => $questions[0]->id, 'answer' => 'blue'],
            ['question_id' => $questions[1]->id, 'answer' => '4'],
        ]);

        app(ActivityService::class)->submitAttempt($activity, $student, [
            ['question_id' => $questions[0]->id, 'answer' => 'red'],
            ['question_id' => $questions[1]->id, 'answer' => '4'],
        ]);

        $this->assertSame(100.0, app(ActivityService::class)->bestPercentage($activity, $student));
    }

    public function test_activities_are_isolated_between_schools(): void
    {
        $schoolA = Organization::factory()->tenant()->create();
        $schoolB = Organization::factory()->tenant()->create();

        $this->activity($schoolA);
        $this->activity($schoolB);

        $this->actingAsTenant($schoolA);
        $this->assertSame(1, InteractiveActivity::query()->count());
        $this->assertSame($schoolA->id, InteractiveActivity::query()->first()->tenant_id);
    }
}

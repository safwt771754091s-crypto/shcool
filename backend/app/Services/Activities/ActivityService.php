<?php

namespace App\Services\Activities;

use App\Models\Activities\ActivityAttempt;
use App\Models\Activities\InteractiveActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Scores interactive activities and, when a competition period is supplied,
 * feeds the result into the academic leaderboard so effort is rewarded.
 */
class ActivityService
{
    /**
     * @param  array<int, array{question_id: int, answer: string}>  $answers
     */
    public function submitAttempt(
        InteractiveActivity $activity,
        User $student,
        array $answers,
        ?int $durationSeconds = null,
    ): ActivityAttempt {
        return DB::transaction(function () use ($activity, $student, $answers, $durationSeconds) {
            $questions = $activity->questions()->get()->keyBy('id');

            $score = 0.0;
            $maxScore = 0.0;
            $normalised = [];

            foreach ($questions as $question) {
                $maxScore += (float) $question->points;
            }

            foreach ($answers as $answer) {
                $question = $questions->get($answer['question_id'] ?? null);

                if ($question === null) {
                    continue;
                }

                $correct = $question->isCorrect((string) ($answer['answer'] ?? ''));
                $awarded = $correct ? (float) $question->points : 0.0;
                $score += $awarded;

                $normalised[] = [
                    'question_id' => $question->getKey(),
                    'answer' => $answer['answer'] ?? null,
                    'correct' => $correct,
                    'points' => $awarded,
                ];
            }

            return ActivityAttempt::create([
                'interactive_activity_id' => $activity->getKey(),
                'student_id' => $student->getKey(),
                'score' => $score,
                'max_score' => $maxScore,
                'duration_seconds' => $durationSeconds,
                'answers' => $normalised,
                'completed_at' => now(),
            ]);
        });
    }

    /**
     * Best attempt of a student for an activity, as a percentage.
     */
    public function bestPercentage(InteractiveActivity $activity, User $student): float
    {
        $best = $activity->attempts()
            ->where('student_id', $student->getKey())
            ->orderByDesc('score')
            ->first();

        return $best?->percentage() ?? 0.0;
    }
}

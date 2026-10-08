<?php

namespace Database\Seeders;

use App\Models\Activities\InteractiveActivity;
use App\Models\Organization;
use App\Support\Tenancy\TenantManager;
use Illuminate\Database\Seeder;

/**
 * Seeds sample interactive activities for primary students: English, Maths,
 * and general activities.
 */
class InteractiveActivitySeeder extends Seeder
{
    public function run(TenantManager $tenants): void
    {
        $school = Organization::query()->where('type', Organization::TYPE_SCHOOL)->first();

        if (! $school) {
            return;
        }

        $tenants->setTenant($school);

        $this->english();
        $this->maths();
        $this->activities();

        $this->command?->info('Interactive activities seeded.');
    }

    protected function english(): void
    {
        $activity = InteractiveActivity::create([
            'title' => 'English Basics — Colours',
            'subject_area' => InteractiveActivity::AREA_ENGLISH,
            'kind' => 'quiz',
            'grade' => 1,
            'time_limit_seconds' => 300,
        ]);

        $activity->questions()->createMany([
            [
                'prompt_en' => 'What colour is the sky?',
                'prompt_ar' => 'ما لون السماء؟',
                'answer_type' => 'choice',
                'options' => ['blue', 'red', 'green'],
                'correct_answer' => 'blue',
                'points' => 2,
                'sequence' => 1,
            ],
            [
                'prompt_en' => 'What colour is grass?',
                'prompt_ar' => 'ما لون العشب؟',
                'answer_type' => 'choice',
                'options' => ['blue', 'green', 'black'],
                'correct_answer' => 'green',
                'points' => 2,
                'sequence' => 2,
            ],
        ]);
    }

    protected function maths(): void
    {
        $activity = InteractiveActivity::create([
            'title' => 'Maths — Addition',
            'subject_area' => InteractiveActivity::AREA_MATH,
            'kind' => 'quiz',
            'grade' => 1,
            'time_limit_seconds' => 240,
        ]);

        $activity->questions()->createMany([
            ['prompt_en' => '2 + 3 = ?', 'prompt_ar' => '٢ + ٣ = ؟', 'correct_answer' => '5', 'points' => 2, 'sequence' => 1],
            ['prompt_en' => '4 + 1 = ?', 'prompt_ar' => '٤ + ١ = ؟', 'correct_answer' => '5', 'points' => 2, 'sequence' => 2],
        ]);
    }

    protected function activities(): void
    {
        $activity = InteractiveActivity::create([
            'title' => 'أنشطة عامة — الأشكال',
            'subject_area' => InteractiveActivity::AREA_ACTIVITIES,
            'kind' => 'game',
            'grade' => 2,
        ]);

        $activity->questions()->create([
            'prompt_en' => 'How many sides does a triangle have?',
            'prompt_ar' => 'كم عدد أضلاع المثلث؟',
            'answer_type' => 'number',
            'correct_answer' => '3',
            'points' => 3,
            'sequence' => 1,
        ]);
    }
}

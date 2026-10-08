<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Platform\MiniApp;
use App\Services\Platform\MiniAppService;
use App\Support\Competition\CompetitionScope;
use Illuminate\Database\Seeder;

/**
 * Seeds a few mini-app templates and publishes them at the right levels:
 * the platform registry, the ministry, the directorate, and the school.
 */
class MiniAppSeeder extends Seeder
{
    public function run(MiniAppService $apps): void
    {
        $ministry = Organization::query()->where('type', Organization::TYPE_MINISTRY)->first();
        $directorate = Organization::query()->where('type', Organization::TYPE_DIRECTORATE)->first();
        $school = Organization::query()->where('type', Organization::TYPE_SCHOOL)->first();

        $templates = [
            ['name' => 'دليل الطالب', 'slug' => 'student-guide', 'category' => 'admin', 'icon' => 'book'],
            ['name' => 'الاختبارات الإلكترونية', 'slug' => 'e-exams', 'category' => 'teaching', 'icon' => 'quiz'],
            ['name' => 'الدوري الرياضي', 'slug' => 'sports-league', 'category' => 'sports', 'icon' => 'trophy'],
            ['name' => 'الأنشطة التفاعلية', 'slug' => 'interactive-activities', 'category' => 'teaching', 'icon' => 'gamepad'],
            ['name' => 'لوحة الترتيب', 'slug' => 'leaderboard', 'category' => 'admin', 'icon' => 'chart'],
        ];

        $models = [];

        foreach ($templates as $template) {
            $models[$template['slug']] = MiniApp::create($template + [
                'supported_scopes' => CompetitionScope::LADDER,
                'is_published' => true,
            ]);
        }

        // National apps published by the ministry.
        foreach (['student-guide', 'e-exams', 'leaderboard'] as $slug) {
            if ($ministry) {
                $apps->publishTo($models[$slug], $ministry);
            }
        }

        // Directorate-level apps.
        if ($directorate) {
            $apps->publishTo($models['sports-league'], $directorate);
        }

        // The school's own app.
        if ($school) {
            $apps->publishTo($models['interactive-activities'], $school);
        }

        $this->command?->info('Mini-app templates and instances seeded.');
    }
}

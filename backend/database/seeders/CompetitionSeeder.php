<?php

namespace Database\Seeders;

use App\Models\Achievement;
use App\Models\CompetitionMetric;
use App\Models\LeaderboardEntry;
use Illuminate\Database\Seeder;

/**
 * Defines the competition scoring metrics, the badges, and a sample ranking
 * period. Weights are the platform's default notion of "excellence".
 */
class CompetitionSeeder extends Seeder
{
    public function run(): void
    {
        $metrics = [
            ['academic.average', 'المعدل الدراسي', 'higher', 5.0, 'متوسط درجات الطالب في الاختبارات'],
            ['attendance.rate', 'نسبة الحضور', 'higher', 3.0, 'نسبة أيام الحضور من إجمالي الأيام'],
            ['behaviour.score', 'السلوك', 'higher', 2.0, 'تقييم السلوك والانتظام'],
            ['activities.participation', 'المشاركة في الأنشطة', 'higher', 1.5, 'عدد الأنشطة التي شارك بها'],
            ['absence.rate', 'نسبة الغياب', 'lower', 2.0, 'كلما قلّت كان أفضل'],
        ];

        $allScopes = LeaderboardEntry::SCOPES;

        foreach ($metrics as [$key, $name, $direction, $weight, $description]) {
            CompetitionMetric::query()->updateOrCreate(
                ['key' => $key],
                [
                    'name' => $name,
                    'description' => $description,
                    'direction' => $direction,
                    'weight' => $weight,
                    'scopes' => $allScopes,
                    'is_active' => true,
                ],
            );
        }

        $achievements = [
            ['first_place', 'المركز الأول', 'student', 100, 'trophy', '#f59e0b'],
            ['top_school', 'أفضل مدرسة', 'school', 250, 'school', '#10b981'],
            ['perfect_attendance', 'حضور كامل', 'student', 50, 'calendar-check', '#3b82f6'],
            ['star_student', 'الطالب المتميز', 'student', 75, 'star', '#8b5cf6'],
            ['best_class', 'أفضل صف', 'class', 150, 'users', '#ef4444'],
        ];

        foreach ($achievements as [$key, $name, $scope, $points, $icon, $color]) {
            Achievement::query()->updateOrCreate(
                ['key' => $key],
                [
                    'name' => $name,
                    'scope_type' => $scope,
                    'points' => $points,
                    'icon' => $icon,
                    'color' => $color,
                    'is_active' => true,
                ],
            );
        }
    }
}

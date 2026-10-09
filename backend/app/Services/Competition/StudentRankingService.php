<?php

namespace App\Services\Competition;

use App\Models\Academic\ClassSection;
use App\Models\Activities\ActivityAttempt;
use App\Models\Attendance\Attendance;
use App\Models\CompetitionMetric;
use App\Models\LeaderboardEntry;
use App\Models\Organization;
use App\Models\RankingPeriod;
use App\Models\Student\Student;
use App\Services\Exams\GradeService;
use Illuminate\Support\Collection;

/**
 * Produces the competition scores that feed every leaderboard.
 *
 * For one school and one period it:
 *   1. measures each enrolled student on every active metric,
 *   2. normalises each metric across the cohort (so the fairest student gets
 *      100 and the weakest 0),
 *   3. writes one CompetitionScore per student/metric, and
 *   4. aggregates the students into per-section LeaderboardEntry rows
 *      (scope=class) with a per-metric breakdown.
 *
 * The class rows are what LeaderboardService then rolls up to school,
 * directorate, governorate and ministry.
 */
class StudentRankingService
{
    public function __construct(
        protected ScoreCalculator $calculator,
        protected GradeService $grades,
    ) {}

    /**
     * Recompute student scores and class entries for a single school.
     *
     * Runs across tenants, so it is expected to be called inside
     * TenantManager::withoutTenancy() or with the school set as the tenant.
     */
    public function recomputeSchool(RankingPeriod $period, Organization $school): void
    {
        $students = Student::allTenants()
            ->where('tenant_id', $school->getKey())
            ->where('status', Student::STATUS_ENROLLED)
            ->get();

        if ($students->isEmpty()) {
            return;
        }

        $metrics = CompetitionMetric::query()->where('is_active', true)->get();
        $raw = $this->measure($period, $students, $metrics);

        $totals = [];   // studentId => total points
        $perMetric = []; // metricKey => [studentId => points]

        foreach ($metrics as $metric) {
            $values = $raw[$metric->key] ?? [];
            $min = $values === [] ? 0.0 : min($values);
            $max = $values === [] ? 0.0 : max($values);

            foreach ($students as $student) {
                $value = $values[$student->getKey()] ?? 0.0;
                $points = $this->calculator->pointsFor($metric, $value, (float) $min, (float) $max);

                $this->calculator->record(
                    tenantId: (int) $school->getKey(),
                    periodId: $period->getKey(),
                    metric: $metric,
                    scorableType: 'student',
                    scorableId: $student->getKey(),
                    rawValue: $value,
                    min: (float) $min,
                    max: (float) $max,
                );

                $perMetric[$metric->key][$student->getKey()] = $points;
                $totals[$student->getKey()] = ($totals[$student->getKey()] ?? 0.0) + $points;
            }
        }

        $this->persistStudentEntries($period, $school, $students, $totals);
        $this->persistClassEntries($period, $school, $students, $totals, $perMetric, $metrics);
    }

    /**
     * Measure every metric for every student.
     *
     * @param  Collection<int, Student>  $students
     * @param  Collection<int, CompetitionMetric>  $metrics
     * @return array<string, array<int, float>> metricKey => [studentId => value]
     */
    protected function measure(RankingPeriod $period, Collection $students, Collection $metrics): array
    {
        $attendance = $this->attendanceRates($period, $students);
        $activities = $this->activityCounts($period, $students);

        $values = [];

        foreach ($metrics as $metric) {
            $values[$metric->key] = match ($metric->key) {
                'academic.average' => $students->mapWithKeys(fn (Student $s) => [
                    $s->getKey() => (float) $this->grades->resultSheet($s)['overall_average'],
                ])->all(),
                'attendance.rate' => $attendance,
                'absence.rate' => array_map(fn (float $rate) => round(100 - $rate, 2), $attendance),
                'activities.participation' => $activities,
                default => $students->mapWithKeys(fn (Student $s) => [$s->getKey() => 0.0])->all(),
            };
        }

        return $values;
    }

    /**
     * Attendance rate (present + late) per student across the period.
     *
     * @param  Collection<int, Student>  $students
     * @return array<int, float>
     */
    protected function attendanceRates(RankingPeriod $period, Collection $students): array
    {
        $ids = $students->modelKeys();

        $rows = Attendance::allTenants()
            ->whereIn('student_id', $ids)
            ->whereHas('session', fn ($q) => $q
                ->whereDate('attendance_date', '>=', $period->starts_on)
                ->whereDate('attendance_date', '<=', $period->ends_on))
            ->get()
            ->groupBy('student_id');

        $rates = [];

        foreach ($students as $student) {
            $records = $rows->get($student->getKey(), collect());
            $total = $records->count();
            $present = $records->whereIn('status', [Attendance::STATUS_PRESENT, Attendance::STATUS_LATE])->count();

            $rates[$student->getKey()] = $total > 0 ? round($present / $total * 100, 2) : 0.0;
        }

        return $rates;
    }

    /**
     * Number of completed activity attempts per student in the period.
     *
     * @param  Collection<int, Student>  $students
     * @return array<int, float>
     */
    protected function activityCounts(RankingPeriod $period, Collection $students): array
    {
        $userIds = $students->pluck('user_id')->filter()->all();

        $counts = $userIds === []
            ? collect()
            : ActivityAttempt::allTenants()
                ->whereIn('student_id', $userIds)
                ->whereDate('completed_at', '>=', $period->starts_on)
                ->whereDate('completed_at', '<=', $period->ends_on)
                ->selectRaw('student_id, count(*) as attempts')
                ->groupBy('student_id')
                ->pluck('attempts', 'student_id');

        $values = [];

        foreach ($students as $student) {
            $values[$student->getKey()] = (float) ($counts[$student->user_id] ?? 0);
        }

        return $values;
    }

    /**
     * Persist one leaderboard row per student (their own rank in the school).
     *
     * @param  Collection<int, Student>  $students
     * @param  array<int, float>  $totals
     */
    protected function persistStudentEntries(
        RankingPeriod $period,
        Organization $school,
        Collection $students,
        array $totals,
    ): void {
        $rows = $students->map(fn (Student $s) => [
            'scope_id' => $s->getKey(),
            'name' => $s->full_name,
            'total_points' => round($totals[$s->getKey()] ?? 0.0, 4),
            'sample_size' => 1,
        ]);

        app(LeaderboardService::class)->persistForTenant($period, LeaderboardEntry::SCOPE_STUDENT, $rows, $school);
    }

    /**
     * Aggregate students into per-section rows and persist them as class scope.
     *
     * @param  Collection<int, Student>  $students
     * @param  array<int, float>  $totals
     * @param  array<string, array<int, float>>  $perMetric
     * @param  Collection<int, CompetitionMetric>  $metrics
     */
    protected function persistClassEntries(
        RankingPeriod $period,
        Organization $school,
        Collection $students,
        array $totals,
        array $perMetric,
        Collection $metrics,
    ): void {
        $sectionNames = ClassSection::allTenants()
            ->whereIn('id', $students->pluck('class_section_id')->filter()->unique()->all())
            ->pluck('name', 'id');

        $bySection = $students->groupBy('class_section_id');

        $rows = $bySection->map(function (Collection $group, $sectionId) use ($totals, $perMetric, $metrics, $sectionNames) {
            $breakdown = [];

            foreach ($metrics as $metric) {
                $points = collect($group)
                    ->map(fn (Student $s) => $perMetric[$metric->key][$s->getKey()] ?? 0.0)
                    ->avg();

                $breakdown[$metric->key] = round((float) $points, 4);
            }

            return [
                'scope_id' => (int) $sectionId,
                'name' => $sectionNames[$sectionId] ?? ('#'.$sectionId),
                'total_points' => round(collect($group)->map(fn (Student $s) => $totals[$s->getKey()] ?? 0.0)->avg(), 4),
                'sample_size' => $group->count(),
                'breakdown' => $breakdown,
            ];
        })->values();

        app(LeaderboardService::class)->persistForTenant($period, LeaderboardEntry::SCOPE_CLASS, $rows, $school);
    }
}

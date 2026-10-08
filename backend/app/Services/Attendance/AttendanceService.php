<?php

namespace App\Services\Attendance;

use App\Models\Attendance\Attendance;
use App\Models\Attendance\AttendanceSession;
use App\Models\Student\Student;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Daily attendance (الحضور) and the absence reports (تقارير الغياب).
 */
class AttendanceService
{
    /**
     * Take the register for a section on a day: create (or reuse) the session
     * and upsert one row per student.
     *
     * @param  array<int, array{student_id: int, status?: string, late_minutes?: int, absence_reason?: string, notes?: string}>  $entries
     */
    public function takeRegister(
        int $classSectionId,
        string $date,
        array $entries,
        ?User $takenBy = null,
        string $period = 'daily',
        ?string $notes = null,
    ): AttendanceSession {
        return DB::transaction(function () use ($classSectionId, $date, $entries, $takenBy, $period, $notes) {
            $on = Carbon::parse($date)->toDateString();

            // The date cast serialises to a datetime string, so match on the day
            // rather than on the raw value.
            $session = AttendanceSession::query()
                ->where('class_section_id', $classSectionId)
                ->whereDate('attendance_date', $on)
                ->where('period', $period)
                ->first();

            if ($session === null) {
                $session = new AttendanceSession([
                    'class_section_id' => $classSectionId,
                    'attendance_date' => $on,
                    'period' => $period,
                ]);
            }

            $session->fill([
                'taken_by' => $takenBy?->getKey(),
                'submitted_at' => now(),
                'notes' => $notes,
            ])->save();

            foreach ($entries as $entry) {
                Attendance::updateOrCreate(
                    [
                        'attendance_session_id' => $session->getKey(),
                        'student_id' => $entry['student_id'],
                    ],
                    [
                        'status' => $entry['status'] ?? Attendance::STATUS_PRESENT,
                        'late_minutes' => $entry['late_minutes'] ?? null,
                        'absence_reason' => $entry['absence_reason'] ?? null,
                        'notes' => $entry['notes'] ?? null,
                    ],
                );
            }

            return $session->refresh();
        });
    }

    /**
     * Summary for one section over a date range: per-student totals and rate.
     *
     * @return array<int, array<string, mixed>>
     */
    public function sectionReport(int $classSectionId, string $from, string $to): array
    {
        $students = Student::query()
            ->where('class_section_id', $classSectionId)
            ->where('status', Student::STATUS_ENROLLED)
            ->orderBy('full_name')
            ->get();

        $rows = Attendance::query()
            ->whereHas('session', function ($query) use ($classSectionId, $from, $to): void {
                $query->where('class_section_id', $classSectionId)
                    ->whereDate('attendance_date', '>=', $from)
                    ->whereDate('attendance_date', '<=', $to);
            })
            ->get()
            ->groupBy('student_id');

        return $students->map(function (Student $student) use ($rows) {
            $records = $rows->get($student->getKey(), collect());
            $total = $records->count();
            $present = $records->where('status', Attendance::STATUS_PRESENT)->count();
            $absent = $records->where('status', Attendance::STATUS_ABSENT)->count();
            $late = $records->where('status', Attendance::STATUS_LATE)->count();
            $excused = $records->where('status', Attendance::STATUS_EXCUSED)->count();

            return [
                'student_id' => $student->getKey(),
                'student_number' => $student->student_number,
                'full_name' => $student->full_name,
                'total' => $total,
                'present' => $present,
                'absent' => $absent,
                'late' => $late,
                'excused' => $excused,
                'rate' => $total > 0 ? round(($present + $late) / $total * 100, 2) : 0.0,
            ];
        })->all();
    }

    /**
     * Students whose absence count crosses the alert threshold in a range.
     *
     * @return array<int, array<string, mixed>>
     */
    public function absenceAlerts(string $from, string $to, int $threshold = 3): array
    {
        return Attendance::query()
            ->where('status', Attendance::STATUS_ABSENT)
            ->whereHas('session', fn ($query) => $query
                ->whereDate('attendance_date', '>=', $from)
                ->whereDate('attendance_date', '<=', $to))
            ->selectRaw('student_id, count(*) as absences')
            ->groupBy('student_id')
            ->havingRaw('count(*) >= ?', [$threshold])
            ->with('student')
            ->orderByDesc('absences')
            ->get()
            ->map(fn (Attendance $row) => [
                'student_id' => $row->student_id,
                'student_number' => $row->student?->student_number,
                'full_name' => $row->student?->full_name,
                'absences' => (int) $row->absences,
            ])
            ->all();
    }
}

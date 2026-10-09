<?php

namespace App\Services\Notifications;

use App\Models\Exam\Exam;
use App\Models\Notification\NotificationTemplate;
use App\Models\Student\Guardian;
use App\Models\Student\Student;
use App\Services\Attendance\AttendanceService;
use App\Services\Exams\GradeService;

/**
 * Turns domain events into notifications (تنبيهات الغياب ونتائج الاختبارات).
 *
 * Kept separate from NotificationService so the trigger rules (who gets told,
 * with which placeholders) live in one place and can be reused by the API and
 * by scheduled commands.
 */
class AlertDispatcher
{
    public function __construct(
        protected NotificationService $notifications,
        protected AttendanceService $attendance,
        protected GradeService $grades,
    ) {}

    /**
     * Notify the guardians of every student who crossed the absence threshold.
     *
     * @return int number of students alerted
     */
    public function absenceAlerts(string $from, string $to, int $threshold = 3): int
    {
        $alerts = $this->attendance->absenceAlerts($from, $to, $threshold);

        foreach ($alerts as $alert) {
            $student = Student::query()->find($alert['student_id']);

            if ($student === null) {
                continue;
            }

            $this->notifyGuardians($student, 'attendance.absence_alert', [
                'student_name' => $student->full_name,
                'absences' => $alert['absences'],
            ]);
        }

        return count($alerts);
    }

    /**
     * Notify a section's guardians (and students with accounts) that results
     * are out.
     */
    public function examPublished(Exam $exam): int
    {
        $students = Student::query()
            ->where('class_section_id', $exam->class_section_id)
            ->where('status', Student::STATUS_ENROLLED)
            ->get();

        $sent = 0;

        foreach ($students as $student) {
            $sheet = $this->grades->resultSheet($student);

            $this->notifyGuardians($student, 'exams.result_published', [
                'student_name' => $student->full_name,
                'average' => $sheet['overall_average'],
            ]);

            $sent++;
        }

        return $sent;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function notifyGuardians(Student $student, string $key, array $data): void
    {
        $guardians = $student->guardians()->get();

        foreach ($guardians as $guardian) {
            /** @var Guardian $guardian */
            $phone = $guardian->phone ?? $guardian->user?->phone;

            if ($phone !== null) {
                $this->notifications->sendToPhone($phone, $key, $data);
            }

            if ($guardian->user_id !== null) {
                $this->notifications->sendToUser(
                    $guardian->user,
                    $key,
                    $data,
                    [NotificationTemplate::CHANNEL_IN_APP],
                );
            }
        }
    }
}

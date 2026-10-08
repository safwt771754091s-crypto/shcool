<?php

namespace App\Services\Sync;

use App\Models\Attendance\Attendance;
use App\Models\Exam\Exam;
use App\Models\Student\Student;
use App\Models\Sync\SyncBatch;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Services\Exams\GradeService;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Offline sync (العمل دون إنترنت).
 *
 * A device queues mutations while offline and replays them as one batch. The
 * batch is idempotent: replaying the same `client_batch_id` returns the stored
 * result instead of applying the changes twice. Each item is applied in its own
 * savepoint so one bad row never rolls back the whole batch.
 */
class SyncService
{
    public function __construct(
        protected AttendanceService $attendance,
        protected GradeService $grades,
    ) {}

    /**
     * Apply (or replay) a batch of offline mutations.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    public function apply(User $user, string $clientBatchId, array $items, ?string $deviceId = null): SyncBatch
    {
        $existing = SyncBatch::query()
            ->where('client_batch_id', $clientBatchId)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($user, $clientBatchId, $items, $deviceId) {
            $results = [];
            $applied = 0;
            $failed = 0;

            foreach ($items as $index => $item) {
                try {
                    $results[] = DB::transaction(function () use ($item) {
                        return $this->applyItem($item);
                    });

                    $applied++;
                } catch (Throwable $e) {
                    $failed++;
                    $results[] = [
                        'client_id' => $item['client_id'] ?? (string) $index,
                        'type' => $item['type'] ?? null,
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                    ];
                }
            }

            return SyncBatch::create([
                'user_id' => $user->getKey(),
                'client_batch_id' => $clientBatchId,
                'device_id' => $deviceId,
                'item_count' => count($items),
                'applied_count' => $applied,
                'failed_count' => $failed,
                'status' => match (true) {
                    $failed === 0 => SyncBatch::STATUS_APPLIED,
                    $applied === 0 => SyncBatch::STATUS_FAILED,
                    default => SyncBatch::STATUS_PARTIAL,
                },
                'results' => $results,
                'received_at' => now(),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function applyItem(array $item): array
    {
        $type = $item['type'] ?? null;
        $payload = $item['payload'] ?? [];

        return match ($type) {
            'attendance.register' => $this->applyAttendance($payload, $item),
            'grade.record' => $this->applyGrade($payload, $item),
            default => throw new \InvalidArgumentException("نوع المزامنة غير مدعوم: {$type}"),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function applyAttendance(array $payload, array $item): array
    {
        $session = $this->attendance->takeRegister(
            (int) $payload['class_section_id'],
            (string) $payload['attendance_date'],
            [[
                'student_id' => (int) $payload['student_id'],
                'status' => $payload['status'] ?? Attendance::STATUS_PRESENT,
                'late_minutes' => $payload['late_minutes'] ?? null,
                'absence_reason' => $payload['absence_reason'] ?? null,
            ]],
            period: $payload['period'] ?? 'daily',
        );

        return [
            'client_id' => $item['client_id'] ?? null,
            'type' => 'attendance.register',
            'status' => 'applied',
            'session_id' => $session->getKey(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function applyGrade(array $payload, array $item): array
    {
        $exam = Exam::query()->findOrFail((int) $payload['exam_id']);
        $student = Student::query()->findOrFail((int) $payload['student_id']);

        $grade = $this->grades->record(
            $exam,
            $student,
            isset($payload['mark']) ? (float) $payload['mark'] : null,
            (bool) ($payload['is_absent'] ?? false),
            $payload['notes'] ?? null,
        );

        return [
            'client_id' => $item['client_id'] ?? null,
            'type' => 'grade.record',
            'status' => 'applied',
            'grade_id' => $grade->getKey(),
        ];
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance\Attendance;
use App\Models\Attendance\AttendanceSession;
use App\Services\Attendance\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Daily attendance (الحضور اليومي) and absence reports (تقارير الغياب).
 */
class AttendanceController extends Controller
{
    public function __construct(protected AttendanceService $attendance) {}

    /**
     * Take the register for a section (one row per student).
     */
    public function takeRegister(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'class_section_id' => ['required', 'integer', 'exists:class_sections,id'],
            'attendance_date' => ['required', 'date'],
            'period' => ['sometimes', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.student_id' => ['required', 'integer', 'exists:students,id'],
            'entries.*.status' => ['sometimes', Rule::in(Attendance::STATUSES)],
            'entries.*.late_minutes' => ['nullable', 'integer', 'min:0'],
            'entries.*.absence_reason' => ['nullable', 'string', 'max:255'],
            'entries.*.notes' => ['nullable', 'string'],
        ]);

        $session = $this->attendance->takeRegister(
            (int) $validated['class_section_id'],
            $validated['attendance_date'],
            $validated['entries'],
            $request->user(),
            $validated['period'] ?? 'daily',
            $validated['notes'] ?? null,
        );

        return response()->json([
            'data' => $session->load('attendances.student'),
            'message' => 'تم تسجيل الحضور.',
        ], 201);
    }

    /**
     * A section's register for one day.
     */
    public function session(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'class_section_id' => ['required', 'integer', 'exists:class_sections,id'],
            'attendance_date' => ['required', 'date'],
            'period' => ['sometimes', 'string', 'max:20'],
        ]);

        $session = AttendanceSession::query()
            ->where('class_section_id', $validated['class_section_id'])
            ->whereDate('attendance_date', $validated['attendance_date'])
            ->where('period', $validated['period'] ?? 'daily')
            ->with('attendances.student')
            ->first();

        return response()->json(['data' => $session]);
    }

    /**
     * Attendance summary for a section over a date range.
     */
    public function report(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'class_section_id' => ['required', 'integer', 'exists:class_sections,id'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        return response()->json([
            'data' => $this->attendance->sectionReport(
                (int) $validated['class_section_id'],
                $validated['from'],
                $validated['to'],
            ),
        ]);
    }

    /**
     * Students with repeated absences in a range.
     */
    public function absenceAlerts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'threshold' => ['sometimes', 'integer', 'min:1', 'max:60'],
        ]);

        return response()->json([
            'data' => $this->attendance->absenceAlerts(
                $validated['from'],
                $validated['to'],
                (int) ($validated['threshold'] ?? 3),
            ),
        ]);
    }
}

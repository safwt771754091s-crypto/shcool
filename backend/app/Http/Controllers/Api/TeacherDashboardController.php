<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance\AttendanceSession;
use App\Models\Exam\Exam;
use App\Models\Staff\Teacher;
use App\Models\Teaching\LessonPreparation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Teacher dashboard (لوحة المعلم): everything a teacher needs on one screen —
 * their assignment load, what they still have to prepare, exams they own and
 * the attendance sessions they took. Made in one request so the Flutter client
 * does not fan out.
 */
class TeacherDashboardController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $teacher = Teacher::query()
            ->where('user_id', $request->user()->getKey())
            ->first();

        if ($teacher === null) {
            return response()->json(['data' => null]);
        }

        $assignments = $teacher->assignments()
            ->with(['subject:id,name', 'section:id,name,school_class_id', 'section.schoolClass:id,name'])
            ->get();

        $preparations = LessonPreparation::query()->where('teacher_id', $teacher->getKey());

        $today = Carbon::now()->toDateString();

        return response()->json([
            'data' => [
                'teacher' => [
                    'id' => $teacher->getKey(),
                    'full_name' => $teacher->full_name,
                    'employee_number' => $teacher->employee_number,
                    'specialization' => $teacher->specialization,
                ],
                'assignments' => [
                    'total' => $assignments->count(),
                    'weekly_periods' => (int) $assignments->sum('weekly_periods'),
                    'subjects' => $assignments->pluck('subject.name')->filter()->unique()->values(),
                    'sections' => $assignments->pluck('section.name')->filter()->unique()->values(),
                    'homeroom' => $assignments->where('is_homeroom', true)->count(),
                    'items' => $assignments,
                ],
                'preparations' => [
                    'draft' => (clone $preparations)->where('status', LessonPreparation::STATUS_DRAFT)->count(),
                    'submitted' => (clone $preparations)->where('status', LessonPreparation::STATUS_SUBMITTED)->count(),
                    'approved' => (clone $preparations)->where('status', LessonPreparation::STATUS_APPROVED)->count(),
                    'returned' => (clone $preparations)->where('status', LessonPreparation::STATUS_RETURNED)->count(),
                    // Anything not yet approved still needs the teacher's hand.
                    'pending' => (clone $preparations)
                        ->whereIn('status', [LessonPreparation::STATUS_DRAFT, LessonPreparation::STATUS_RETURNED])
                        ->count(),
                ],
                'attendance' => [
                    'sessions_taken' => AttendanceSession::query()
                        ->where('taken_by', $request->user()->getKey())
                        ->count(),
                    'sessions_today' => AttendanceSession::query()
                        ->where('taken_by', $request->user()->getKey())
                        ->whereDate('attendance_date', $today)
                        ->count(),
                ],
                'exams' => [
                    'created' => Exam::query()->where('created_by', $request->user()->getKey())->count(),
                    'upcoming' => Exam::query()
                        ->whereIn('class_section_id', $assignments->pluck('class_section_id')->unique())
                        ->whereDate('held_on', '>=', $today)
                        ->orderBy('held_on')
                        ->limit(5)
                        ->get(['id', 'title', 'held_on', 'max_mark', 'class_section_id']),
                ],
            ],
        ]);
    }
}

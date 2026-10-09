<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance\Attendance;
use App\Models\Exam\Exam;
use App\Models\Student\Guardian;
use App\Models\Student\Student;
use App\Services\Exams\GradeService;
use App\Services\Finance\FinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Parent and student portals (بوابة أولياء الأمور والطلاب).
 *
 * A parent may only ever read their own children; a student only their own
 * record. Both endpoints return a single consolidated payload so the Flutter
 * client makes one call per screen.
 */
class PortalController extends Controller
{
    public function __construct(
        protected GradeService $grades,
        protected FinanceService $finance,
    ) {}

    /**
     * Dashboard for the signed-in parent: their children plus, for each child,
     * attendance summary and latest result.
     */
    public function parent(Request $request): JsonResponse
    {
        $guardian = Guardian::query()
            ->where('user_id', $request->user()->getKey())
            ->first();

        if ($guardian === null) {
            return response()->json(['data' => ['children' => []]]);
        }

        $children = $guardian->students()
            ->with('section.schoolClass')
            ->get()
            ->map(fn (Student $student) => $this->childSummary($student))
            ->all();

        return response()->json([
            'data' => [
                'guardian' => [
                    'id' => $guardian->getKey(),
                    'full_name' => $guardian->full_name,
                    'relation' => $guardian->relation,
                ],
                'children' => $children,
            ],
        ]);
    }

    /**
     * Dashboard for the signed-in student.
     */
    public function student(Request $request): JsonResponse
    {
        $student = Student::query()
            ->where('user_id', $request->user()->getKey())
            ->with('section.schoolClass')
            ->first();

        if ($student === null) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => $this->childSummary($student, includeUpcoming: true),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function childSummary(Student $student, bool $includeUpcoming = false): array
    {
        $from = Carbon::now()->startOfMonth()->toDateString();
        $to = Carbon::now()->toDateString();

        $records = Attendance::query()
            ->where('student_id', $student->getKey())
            ->whereHas('session', fn ($q) => $q
                ->whereDate('attendance_date', '>=', $from)
                ->whereDate('attendance_date', '<=', $to))
            ->get();

        $total = $records->count();

        $summary = [
            'student_id' => $student->getKey(),
            'student_number' => $student->student_number,
            'full_name' => $student->full_name,
            'section' => $student->section?->name,
            'class' => $student->section?->schoolClass?->name,
            'attendance' => [
                'total' => $total,
                'present' => $records->where('status', Attendance::STATUS_PRESENT)->count(),
                'absent' => $records->where('status', Attendance::STATUS_ABSENT)->count(),
                'late' => $records->where('status', Attendance::STATUS_LATE)->count(),
                'rate' => $total > 0
                    ? round($records->whereIn('status', [Attendance::STATUS_PRESENT, Attendance::STATUS_LATE])->count() / $total * 100, 2)
                    : 0.0,
            ],
            'result' => $this->grades->resultSheet($student),
            'balance' => $this->finance->studentBalance($student),
        ];

        if ($includeUpcoming) {
            $summary['upcoming_exams'] = Exam::query()
                ->where('class_section_id', $student->class_section_id)
                ->whereDate('held_on', '>=', $to)
                ->orderBy('held_on')
                ->limit(5)
                ->get(['id', 'title', 'held_on', 'max_mark']);
        }

        return $summary;
    }
}

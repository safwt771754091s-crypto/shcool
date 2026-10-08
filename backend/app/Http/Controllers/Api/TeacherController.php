<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Staff\Teacher;
use App\Models\Staff\TeachingAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Teachers (المعلمون) and their teaching assignments (التوزيع).
 */
class TeacherController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in([
                Teacher::STATUS_ACTIVE,
                Teacher::STATUS_LEAVE,
                Teacher::STATUS_RETIRED,
                Teacher::STATUS_TERMINATED,
            ])],
            'search' => ['sometimes', 'string', 'max:100'],
        ]);

        $query = Teacher::query()->withCount('assignments')->orderBy('full_name');

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if ($search = $validated['search'] ?? null) {
            $query->where(function ($q) use ($search): void {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%");
            });
        }

        return response()->json(['data' => $query->paginate(50)]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'employee_number' => ['required', 'string', 'max:40'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'birth_date' => ['nullable', 'date'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'specialization' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'qualification' => ['nullable', 'string', 'max:255'],
            'hired_on' => ['nullable', 'date'],
        ]);

        $teacher = Teacher::create($validated);

        return response()->json([
            'data' => $teacher,
            'message' => 'تم إضافة المعلم.',
        ], 201);
    }

    public function show(Teacher $teacher): JsonResponse
    {
        return response()->json([
            'data' => $teacher->load(['assignments.subject', 'assignments.section']),
        ]);
    }

    public function update(Request $request, Teacher $teacher): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => ['sometimes', 'string', 'max:255'],
            'specialization' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'qualification' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['sometimes', Rule::in([
                Teacher::STATUS_ACTIVE,
                Teacher::STATUS_LEAVE,
                Teacher::STATUS_RETIRED,
                Teacher::STATUS_TERMINATED,
            ])],
        ]);

        $teacher->update($validated);

        return response()->json(['data' => $teacher->refresh()]);
    }

    /**
     * Assign a teacher to a (subject, section) pair.
     */
    public function assign(Request $request, Teacher $teacher): JsonResponse
    {
        $validated = $request->validate([
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'class_section_id' => ['required', 'integer', 'exists:class_sections,id'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'weekly_periods' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'is_homeroom' => ['sometimes', 'boolean'],
        ]);

        $assignment = TeachingAssignment::updateOrCreate(
            [
                'teacher_id' => $teacher->getKey(),
                'subject_id' => $validated['subject_id'],
                'class_section_id' => $validated['class_section_id'],
                'academic_year_id' => $validated['academic_year_id'] ?? null,
            ],
            [
                'weekly_periods' => $validated['weekly_periods'] ?? 1,
                'is_homeroom' => (bool) ($validated['is_homeroom'] ?? false),
                'is_active' => true,
            ],
        );

        return response()->json([
            'data' => $assignment->load(['subject', 'section']),
            'message' => 'تم توزيع المعلم على المادة والشعبة.',
        ], 201);
    }

    /**
     * The signed-in teacher's own assignments.
     */
    public function myAssignments(Request $request): JsonResponse
    {
        $teacher = Teacher::query()
            ->where('user_id', $request->user()->getKey())
            ->first();

        if ($teacher === null) {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => $teacher->assignments()->with(['subject', 'section.schoolClass'])->get(),
        ]);
    }
}

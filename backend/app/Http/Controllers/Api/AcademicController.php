<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\ClassSection;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Subject;
use App\Models\Academic\Term;
use App\Models\Teaching\CurriculumUnit;
use App\Models\Teaching\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Academic structure: years, terms, subjects, classes, sections, and the
 * curriculum tree (units -> lessons).
 */
class AcademicController extends Controller
{
    // ---- Academic years & terms --------------------------------------

    public function years(): JsonResponse
    {
        return response()->json([
            'data' => AcademicYear::query()->with('terms')->orderByDesc('starts_on')->get(),
        ]);
    }

    public function storeYear(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'is_current' => ['sometimes', 'boolean'],
        ]);

        $year = AcademicYear::create($validated);

        if ($validated['is_current'] ?? false) {
            $year->markAsCurrent();
        }

        return response()->json(['data' => $year, 'message' => 'تم إنشاء السنة الدراسية.'], 201);
    }

    public function storeTerm(Request $request, AcademicYear $year): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'sequence' => ['required', 'integer', 'min:1', 'max:4'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'is_current' => ['sometimes', 'boolean'],
        ]);

        $term = $year->terms()->create($validated);

        return response()->json(['data' => $term, 'message' => 'تم إنشاء الفصل الدراسي.'], 201);
    }

    // ---- Subjects ----------------------------------------------------

    public function subjects(): JsonResponse
    {
        return response()->json(['data' => Subject::query()->orderBy('name')->get()]);
    }

    public function storeSubject(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:30'],
            'stage' => ['nullable', Rule::in(['primary', 'intermediate', 'secondary'])],
            'pass_mark' => ['sometimes', 'numeric', 'min:0'],
            'max_mark' => ['sometimes', 'numeric', 'gt:pass_mark'],
        ]);

        $subject = Subject::create($validated);

        return response()->json(['data' => $subject, 'message' => 'تم إنشاء المادة.'], 201);
    }

    // ---- Classes & sections ------------------------------------------

    public function classes(): JsonResponse
    {
        return response()->json([
            'data' => SchoolClass::query()->with('sections')->orderBy('grade')->get(),
        ]);
    }

    public function storeClass(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'grade' => ['required', 'integer', 'min:1', 'max:12'],
            'stage' => ['nullable', Rule::in(['primary', 'intermediate', 'secondary'])],
            'branch_id' => ['nullable', 'integer', 'exists:organizations,id'],
        ]);

        $class = SchoolClass::create($validated);

        return response()->json(['data' => $class, 'message' => 'تم إنشاء الصف.'], 201);
    }

    public function storeSection(Request $request, SchoolClass $class): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'capacity' => ['sometimes', 'integer', 'min:1'],
            'room' => ['nullable', 'string', 'max:50'],
            'homeroom_teacher_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $section = $class->sections()->create($validated);

        return response()->json(['data' => $section, 'message' => 'تم إنشاء الشعبة.'], 201);
    }

    // ---- Curriculum --------------------------------------------------

    public function units(Request $request): JsonResponse
    {
        $query = CurriculumUnit::query()->with('lessons')->orderBy('sequence');

        if ($subjectId = $request->integer('subject_id')) {
            $query->where('subject_id', $subjectId);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function storeUnit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'term_id' => ['nullable', 'integer', 'exists:terms,id'],
            'name' => ['required', 'string', 'max:255'],
            'sequence' => ['sometimes', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
        ]);

        $unit = CurriculumUnit::create($validated);

        return response()->json(['data' => $unit, 'message' => 'تم إنشاء الوحدة.'], 201);
    }

    public function storeLesson(Request $request, CurriculumUnit $unit): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'sequence' => ['sometimes', 'integer', 'min:1'],
            'planned_periods' => ['sometimes', 'integer', 'min:1'],
            'objectives' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
        ]);

        $lesson = $unit->lessons()->create($validated);

        return response()->json(['data' => $lesson, 'message' => 'تم إنشاء الدرس.'], 201);
    }
}

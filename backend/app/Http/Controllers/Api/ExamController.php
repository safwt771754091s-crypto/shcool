<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam\Exam;
use App\Models\Student\Student;
use App\Services\Exams\GradeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Exams (الاختبارات), grade entry (رصد الدرجات) and result sheets (كشوف النتائج).
 */
class ExamController extends Controller
{
    public function __construct(protected GradeService $grades) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'class_section_id' => ['sometimes', 'integer', 'exists:class_sections,id'],
            'subject_id' => ['sometimes', 'integer', 'exists:subjects,id'],
            'term_id' => ['sometimes', 'integer', 'exists:terms,id'],
        ]);

        $query = Exam::query()
            ->with(['subject', 'section.schoolClass', 'term'])
            ->withCount('grades')
            ->orderByDesc('held_on');

        foreach (['class_section_id', 'subject_id', 'term_id'] as $field) {
            if (isset($validated[$field])) {
                $query->where($field, $validated[$field]);
            }
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'class_section_id' => ['required', 'integer', 'exists:class_sections,id'],
            'term_id' => ['nullable', 'integer', 'exists:terms,id'],
            'type' => ['sometimes', Rule::in([
                Exam::TYPE_DAILY,
                Exam::TYPE_MONTHLY,
                Exam::TYPE_MIDTERM,
                Exam::TYPE_FINAL,
                Exam::TYPE_QUIZ,
            ])],
            'held_on' => ['nullable', 'date'],
            'max_mark' => ['sometimes', 'numeric', 'min:1'],
            'pass_mark' => ['sometimes', 'numeric', 'min:0'],
            'weight' => ['sometimes', 'numeric', 'min:0'],
        ]);

        $exam = Exam::create(array_merge($validated, [
            'created_by' => $request->user()->getKey(),
        ]));

        return response()->json([
            'data' => $exam->load('subject'),
            'message' => 'تم إنشاء الاختبار.',
        ], 201);
    }

    public function show(Exam $exam): JsonResponse
    {
        return response()->json([
            'data' => $exam->load(['subject', 'section', 'grades.student']),
        ]);
    }

    /**
     * Bulk grade entry for an exam.
     */
    public function recordGrades(Request $request, Exam $exam): JsonResponse
    {
        $validated = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.student_id' => ['required', 'integer', 'exists:students,id'],
            'rows.*.mark' => ['nullable', 'numeric', 'min:0'],
            'rows.*.is_absent' => ['sometimes', 'boolean'],
            'rows.*.notes' => ['nullable', 'string'],
        ]);

        $grades = $this->grades->recordMany($exam, $validated['rows'], $request->user());

        return response()->json([
            'data' => $grades,
            'message' => 'تم رصد الدرجات.',
        ]);
    }

    /**
     * Publish an exam's results.
     */
    public function publish(Exam $exam): JsonResponse
    {
        $exam->forceFill(['is_published' => true])->save();

        return response()->json([
            'data' => $exam,
            'message' => 'تم نشر نتائج الاختبار.',
        ]);
    }

    /**
     * One student's result sheet.
     */
    public function resultSheet(Request $request, Student $student): JsonResponse
    {
        $termId = $request->integer('term_id') ?: null;

        return response()->json(['data' => $this->grades->resultSheet($student, $termId)]);
    }

    /**
     * The whole section's result sheet, ranked.
     */
    public function sectionResultSheet(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'class_section_id' => ['required', 'integer', 'exists:class_sections,id'],
            'term_id' => ['nullable', 'integer', 'exists:terms,id'],
        ]);

        return response()->json([
            'data' => $this->grades->sectionResultSheet(
                (int) $validated['class_section_id'],
                $validated['term_id'] ?? null,
            ),
        ]);
    }

    /**
     * The signed-in student's own result sheet.
     */
    public function myResultSheet(Request $request): JsonResponse
    {
        $student = Student::query()
            ->where('user_id', $request->user()->getKey())
            ->first();

        if ($student === null) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => $this->grades->resultSheet($student, $request->integer('term_id') ?: null),
        ]);
    }
}

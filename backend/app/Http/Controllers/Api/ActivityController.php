<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activities\InteractiveActivity;
use App\Services\Activities\ActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Interactive activities for primary students (English / Maths / activities).
 */
class ActivityController extends Controller
{
    public function __construct(protected ActivityService $activities)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = InteractiveActivity::query()
            ->where('is_active', true)
            ->withCount('questions')
            ->orderBy('grade')
            ->orderBy('title');

        if ($area = $request->string('subject_area')->toString()) {
            $query->where('subject_area', $area);
        }

        if ($grade = $request->integer('grade')) {
            $query->where('grade', $grade);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subject_area' => ['required', Rule::in([
                InteractiveActivity::AREA_ENGLISH,
                InteractiveActivity::AREA_MATH,
                InteractiveActivity::AREA_ACTIVITIES,
            ])],
            'kind' => ['sometimes', 'string', 'max:20'],
            'grade' => ['nullable', 'integer', 'min:1', 'max:12'],
            'time_limit_seconds' => ['nullable', 'integer', 'min:10'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.prompt_en' => ['required', 'string'],
            'questions.*.prompt_ar' => ['nullable', 'string'],
            'questions.*.answer_type' => ['sometimes', 'string', 'max:20'],
            'questions.*.options' => ['nullable', 'array'],
            'questions.*.correct_answer' => ['required', 'string'],
            'questions.*.points' => ['sometimes', 'numeric', 'min:0'],
        ]);

        $activity = InteractiveActivity::create(array_merge(
            collect($validated)->except('questions')->all(),
            ['created_by' => $request->user()->getKey(), 'is_active' => true],
        ));

        foreach ($validated['questions'] as $index => $question) {
            $activity->questions()->create(array_merge($question, [
                'sequence' => $index + 1,
            ]));
        }

        return response()->json([
            'data' => $activity->load('questions'),
            'message' => 'تم إنشاء النشاط التفاعلي.',
        ], 201);
    }

    public function show(InteractiveActivity $activity): JsonResponse
    {
        return response()->json(['data' => $activity->load('questions')]);
    }

    /**
     * A student submits their answers; the attempt is scored server-side.
     */
    public function submit(Request $request, InteractiveActivity $activity): JsonResponse
    {
        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*.question_id' => ['required', 'integer'],
            'answers.*.answer' => ['nullable'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
        ]);

        $attempt = $this->activities->submitAttempt(
            $activity,
            $request->user(),
            $validated['answers'],
            $validated['duration_seconds'] ?? null,
        );

        return response()->json([
            'data' => $attempt,
            'percentage' => $attempt->percentage(),
            'message' => 'تم إرسال الإجابات.',
        ], 201);
    }

    /**
     * The signed-in student's best results.
     */
    public function myResults(Request $request): JsonResponse
    {
        $attempts = \App\Models\Activities\ActivityAttempt::query()
            ->where('student_id', $request->user()->getKey())
            ->with('activity')
            ->orderByDesc('score')
            ->get();

        return response()->json(['data' => $attempts]);
    }
}

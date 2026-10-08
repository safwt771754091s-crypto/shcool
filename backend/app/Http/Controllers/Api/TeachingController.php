<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Teaching\LessonPreparation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The teacher's preparation notebook (كراسة التحضير) and everything around it.
 */
class TeachingController extends Controller
{
    /**
     * Preparations, filtered by teacher / section / status / date range.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'teacher_id' => ['sometimes', 'integer', 'exists:users,id'],
            'class_section_id' => ['sometimes', 'integer', 'exists:class_sections,id'],
            'status' => ['sometimes', Rule::in([
                LessonPreparation::STATUS_DRAFT,
                LessonPreparation::STATUS_SUBMITTED,
                LessonPreparation::STATUS_APPROVED,
                LessonPreparation::STATUS_RETURNED,
            ])],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
        ]);

        $query = LessonPreparation::query()
            ->with(['lesson.unit.subject', 'section', 'teacher'])
            ->orderByDesc('scheduled_on');

        // A teacher only ever sees their own notebook.
        if ($request->user()->hasRole(\App\Support\Permission\Roles::TEACHER)) {
            $query->where('teacher_id', $request->user()->getKey());
        } elseif ($teacherId = $validated['teacher_id'] ?? null) {
            $query->where('teacher_id', $teacherId);
        }

        foreach (['class_section_id', 'status'] as $field) {
            if (isset($validated[$field])) {
                $query->where($field, $validated[$field]);
            }
        }

        if (isset($validated['from'])) {
            $query->whereDate('scheduled_on', '>=', $validated['from']);
        }

        if (isset($validated['to'])) {
            $query->whereDate('scheduled_on', '<=', $validated['to']);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lesson_id' => ['required', 'exists:lessons,id'],
            'class_section_id' => ['nullable', 'exists:class_sections,id'],
            'scheduled_on' => ['required', 'date'],
            'objectives' => ['nullable', 'string'],
            'strategies' => ['nullable', 'string'],
            'resources' => ['nullable', 'string'],
            'homework' => ['nullable', 'string'],
            'assessment' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $preparation = LessonPreparation::create(array_merge($validated, [
            'teacher_id' => $request->user()->getKey(),
            'status' => LessonPreparation::STATUS_DRAFT,
        ]));

        return response()->json([
            'data' => $preparation->load('lesson'),
            'message' => 'تم حفظ التحضير.',
        ], 201);
    }

    public function update(Request $request, LessonPreparation $preparation): JsonResponse
    {
        $this->authorizeOwner($request, $preparation);

        if (! $preparation->isEditable()) {
            return response()->json(['message' => 'لا يمكن تعديل تحضير تم اعتماده.'], 422);
        }

        $validated = $request->validate([
            'scheduled_on' => ['sometimes', 'date'],
            'objectives' => ['nullable', 'string'],
            'strategies' => ['nullable', 'string'],
            'resources' => ['nullable', 'string'],
            'homework' => ['nullable', 'string'],
            'assessment' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $preparation->update($validated);

        return response()->json(['data' => $preparation->refresh()]);
    }

    /**
     * Teacher submits the preparation for review.
     */
    public function submit(Request $request, LessonPreparation $preparation): JsonResponse
    {
        $this->authorizeOwner($request, $preparation);

        $preparation->forceFill(['status' => LessonPreparation::STATUS_SUBMITTED])->save();

        return response()->json([
            'data' => $preparation,
            'message' => 'تم إرسال التحضير للمراجعة.',
        ]);
    }

    /**
     * Supervisor approves or returns a preparation.
     */
    public function review(Request $request, LessonPreparation $preparation): JsonResponse
    {
        $validated = $request->validate([
            'approved' => ['required', 'boolean'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $preparation->review(
            (bool) $validated['approved'],
            $request->user(),
            $validated['comment'] ?? null,
        );

        return response()->json([
            'data' => $preparation->refresh(),
            'message' => $validated['approved'] ? 'تم اعتماد التحضير.' : 'تم إرجاع التحضير للتعديل.',
        ]);
    }

    protected function authorizeOwner(Request $request, LessonPreparation $preparation): void
    {
        $user = $request->user();

        abort_unless(
            $user->getKey() === $preparation->teacher_id
                || $user->can('teaching.review')
                || $user->can('teaching.approve'),
            403,
            'لا تملك صلاحية على هذا التحضير.',
        );
    }
}

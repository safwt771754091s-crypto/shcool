<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student\Guardian;
use App\Models\Student\Student;
use App\Services\Students\StudentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Guardians / parents (أولياء الأمور) and their link to students.
 */
class GuardianController extends Controller
{
    public function __construct(protected StudentService $students) {}

    public function index(Request $request): JsonResponse
    {
        $query = Guardian::query()
            ->withCount('students')
            ->orderBy('full_name');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search): void {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return response()->json(['data' => $query->paginate(50)]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'relation' => ['sometimes', Rule::in([
                Guardian::RELATION_FATHER,
                Guardian::RELATION_MOTHER,
                Guardian::RELATION_GUARDIAN,
            ])],
            'phone' => ['nullable', 'string', 'max:30'],
            'alt_phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $guardian = Guardian::create($validated);

        return response()->json([
            'data' => $guardian,
            'message' => 'تم إضافة ولي الأمر.',
        ], 201);
    }

    public function show(Guardian $guardian): JsonResponse
    {
        return response()->json(['data' => $guardian->load('students.section')]);
    }

    public function update(Request $request, Guardian $guardian): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => ['sometimes', 'string', 'max:255'],
            'relation' => ['sometimes', Rule::in([
                Guardian::RELATION_FATHER,
                Guardian::RELATION_MOTHER,
                Guardian::RELATION_GUARDIAN,
            ])],
            'phone' => ['nullable', 'string', 'max:30'],
            'alt_phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $guardian->update($validated);

        return response()->json(['data' => $guardian->refresh()]);
    }

    /**
     * Link a guardian to a student.
     */
    public function link(Request $request, Guardian $guardian): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'relation' => ['sometimes', Rule::in([
                Guardian::RELATION_FATHER,
                Guardian::RELATION_MOTHER,
                Guardian::RELATION_GUARDIAN,
            ])],
            'is_primary' => ['sometimes', 'boolean'],
        ]);

        $student = Student::query()->findOrFail($validated['student_id']);

        $this->students->linkGuardian(
            $student,
            $guardian,
            $validated['relation'] ?? $guardian->relation,
            (bool) ($validated['is_primary'] ?? false),
        );

        return response()->json([
            'data' => $guardian->load('students'),
            'message' => 'تم ربط ولي الأمر بالطالب.',
        ]);
    }
}

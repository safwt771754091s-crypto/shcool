<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student\Admission;
use App\Models\Student\Guardian;
use App\Models\Student\Student;
use App\Services\Students\StudentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Students (الطلاب) and the admission workflow (القبول).
 */
class StudentController extends Controller
{
    public function __construct(protected StudentService $students) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'class_section_id' => ['sometimes', 'integer', 'exists:class_sections,id'],
            'status' => ['sometimes', Rule::in([
                Student::STATUS_APPLICANT,
                Student::STATUS_ENROLLED,
                Student::STATUS_GRADUATED,
                Student::STATUS_WITHDRAWN,
                Student::STATUS_TRANSFERRED,
            ])],
            'search' => ['sometimes', 'string', 'max:100'],
        ]);

        $query = Student::query()
            ->with('section.schoolClass')
            ->orderBy('full_name');

        foreach (['class_section_id', 'status'] as $field) {
            if (isset($validated[$field])) {
                $query->where($field, $validated[$field]);
            }
        }

        if ($search = $validated['search'] ?? null) {
            $query->where(function ($q) use ($search): void {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('student_number', 'like', "%{$search}%");
            });
        }

        return response()->json(['data' => $query->paginate(50)]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'student_number' => ['required', 'string', 'max:40'],
            'class_section_id' => ['nullable', 'integer', 'exists:class_sections,id'],
            'gender' => ['nullable', Rule::in([Student::GENDER_MALE, Student::GENDER_FEMALE])],
            'birth_date' => ['nullable', 'date'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $student = $this->students->enrol($validated, $request->user());

        return response()->json([
            'data' => $student->load('section'),
            'message' => 'تم تسجيل الطالب.',
        ], 201);
    }

    public function show(Student $student): JsonResponse
    {
        return response()->json([
            'data' => $student->load(['section.schoolClass', 'guardians']),
        ]);
    }

    public function update(Request $request, Student $student): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => ['sometimes', 'string', 'max:255'],
            'gender' => ['nullable', Rule::in([Student::GENDER_MALE, Student::GENDER_FEMALE])],
            'birth_date' => ['nullable', 'date'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $student->update($validated);

        return response()->json(['data' => $student->refresh()]);
    }

    /**
     * Move a student to a different section.
     */
    public function transfer(Request $request, Student $student): JsonResponse
    {
        $validated = $request->validate([
            'class_section_id' => ['required', 'integer', 'exists:class_sections,id'],
        ]);

        $student = $this->students->transferToSection($student, (int) $validated['class_section_id']);

        return response()->json([
            'data' => $student->load('section'),
            'message' => 'تم نقل الطالب إلى الشعبة الجديدة.',
        ]);
    }

    /**
     * Change the student lifecycle status (graduate / withdraw / transfer).
     */
    public function changeStatus(Request $request, Student $student): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                Student::STATUS_ENROLLED,
                Student::STATUS_GRADUATED,
                Student::STATUS_WITHDRAWN,
                Student::STATUS_TRANSFERRED,
            ])],
        ]);

        $student = $this->students->changeStatus($student, $validated['status']);

        return response()->json(['data' => $student]);
    }

    /**
     * The signed-in parent's children.
     */
    public function myChildren(Request $request): JsonResponse
    {
        $guardian = Guardian::query()
            ->where('user_id', $request->user()->getKey())
            ->first();

        if ($guardian === null) {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => $this->students->studentsOfGuardian($guardian)->load('section.schoolClass'),
        ]);
    }

    // ---- Admissions ---------------------------------------------------

    public function admissions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in([
                Admission::STATUS_SUBMITTED,
                Admission::STATUS_ACCEPTED,
                Admission::STATUS_REJECTED,
                Admission::STATUS_ENROLLED,
            ])],
        ]);

        $query = Admission::query()
            ->with(['schoolClass', 'academicYear'])
            ->orderByDesc('created_at');

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function storeAdmission(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'applicant_name' => ['required', 'string', 'max:255'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'school_class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
            'gender' => ['nullable', Rule::in([Student::GENDER_MALE, Student::GENDER_FEMALE])],
            'birth_date' => ['nullable', 'date'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'guardian_name' => ['nullable', 'string', 'max:255'],
            'guardian_phone' => ['nullable', 'string', 'max:30'],
            'previous_school' => ['nullable', 'string', 'max:255'],
        ]);

        $admission = Admission::create($validated);

        return response()->json([
            'data' => $admission,
            'message' => 'تم استلام طلب القبول.',
        ], 201);
    }

    /**
     * Accept or reject an application.
     */
    public function decideAdmission(Request $request, Admission $admission): JsonResponse
    {
        $validated = $request->validate([
            'accepted' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:1000'],
            'score' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        if (isset($validated['score'])) {
            $admission->forceFill(['score' => $validated['score']])->save();
        }

        $admission = $this->students->decide(
            $admission,
            (bool) $validated['accepted'],
            $request->user(),
            $validated['note'] ?? null,
        );

        return response()->json([
            'data' => $admission,
            'message' => $validated['accepted'] ? 'تم قبول الطالب.' : 'تم رفض الطلب.',
        ]);
    }

    /**
     * Enrol an accepted applicant, creating the student profile.
     */
    public function enrolAdmission(Request $request, Admission $admission): JsonResponse
    {
        $validated = $request->validate([
            'class_section_id' => ['required', 'integer', 'exists:class_sections,id'],
            'student_number' => ['required', 'string', 'max:40'],
        ]);

        $student = $this->students->enrolFromAdmission($admission, $validated);

        return response()->json([
            'data' => $student->load('section'),
            'message' => 'تم قبول الطالب وتسجيله.',
        ], 201);
    }
}

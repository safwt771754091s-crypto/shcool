<?php

namespace App\Services\Students;

use App\Models\Student\Admission;
use App\Models\Student\Guardian;
use App\Models\Student\Student;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Enrolment (القبول), the student profile, and the guardian links.
 */
class StudentService
{
    /**
     * Enrol a student: create the profile in `enrolled` status.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function enrol(array $attributes, ?User $actor = null): Student
    {
        return DB::transaction(function () use ($attributes) {
            $attributes['status'] = Student::STATUS_ENROLLED;
            $attributes['admitted_at'] ??= now()->toDateString();

            return Student::create($attributes);
        });
    }

    /**
     * Turn an accepted admission into a real student profile and mark the
     * application enrolled.
     *
     * @param  array<string, mixed>  $extra  section, student_number, etc.
     */
    public function enrolFromAdmission(Admission $admission, array $extra = []): Student
    {
        return DB::transaction(function () use ($admission, $extra) {
            $student = Student::create(array_merge([
                'full_name' => $admission->applicant_name,
                'gender' => $admission->gender,
                'birth_date' => $admission->birth_date,
                'national_id' => $admission->national_id,
                'class_section_id' => $extra['class_section_id'] ?? null,
            ], $extra, [
                'status' => Student::STATUS_ENROLLED,
                'admitted_at' => now()->toDateString(),
            ]));

            $admission->forceFill([
                'status' => Admission::STATUS_ENROLLED,
                'student_id' => $student->getKey(),
            ])->save();

            return $student;
        });
    }

    /**
     * Record an admission decision.
     */
    public function decide(Admission $admission, bool $accepted, ?User $actor = null, ?string $note = null): Admission
    {
        $admission->forceFill([
            'status' => $accepted ? Admission::STATUS_ACCEPTED : Admission::STATUS_REJECTED,
            'decision_note' => $note,
            'decided_by' => $actor?->getKey(),
            'decided_at' => now(),
        ])->save();

        return $admission;
    }

    /**
     * Move a student to another section, keeping the school the same.
     */
    public function transferToSection(Student $student, int $classSectionId): Student
    {
        $student->forceFill(['class_section_id' => $classSectionId])->save();

        return $student;
    }

    public function changeStatus(Student $student, string $status): Student
    {
        $student->forceFill(['status' => $status])->save();

        return $student;
    }

    /**
     * Link a guardian to a student (idempotent on the pair).
     */
    public function linkGuardian(Student $student, Guardian $guardian, string $relation, bool $isPrimary = false): void
    {
        $student->guardians()->syncWithoutDetaching([
            $guardian->getKey() => [
                'tenant_id' => $student->tenant_id,
                'relation' => $relation,
                'is_primary' => $isPrimary,
            ],
        ]);
    }

    /**
     * All students a guardian may see.
     *
     * @return Collection<int, Student>
     */
    public function studentsOfGuardian(Guardian $guardian)
    {
        return $guardian->students()->get();
    }
}

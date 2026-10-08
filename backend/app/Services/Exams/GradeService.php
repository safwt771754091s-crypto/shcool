<?php

namespace App\Services\Exams;

use App\Models\Exam\Exam;
use App\Models\Exam\Grade;
use App\Models\Student\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Grade entry (رصد الدرجات) and result sheets (كشوف النتائج).
 */
class GradeService
{
    /**
     * Record one student's mark, honouring absence and the exam's max mark.
     */
    public function record(
        Exam $exam,
        Student $student,
        ?float $mark,
        bool $absent = false,
        ?string $notes = null,
        ?User $enteredBy = null,
    ): Grade {
        if (! $absent && $mark !== null && $mark > (float) $exam->max_mark) {
            throw new \InvalidArgumentException('الدرجة تتجاوز الدرجة العظمى للاختبار.');
        }

        return Grade::updateOrCreate(
            ['exam_id' => $exam->getKey(), 'student_id' => $student->getKey()],
            [
                'mark' => $absent ? null : $mark,
                'is_absent' => $absent,
                'notes' => $notes,
                'entered_by' => $enteredBy?->getKey(),
                'entered_at' => now(),
            ],
        );
    }

    /**
     * Bulk entry for a whole section.
     *
     * @param  array<int, array{student_id: int, mark?: float|null, is_absent?: bool, notes?: string}>  $rows
     * @return array<int, Grade>
     */
    public function recordMany(Exam $exam, array $rows, ?User $enteredBy = null): array
    {
        return DB::transaction(function () use ($exam, $rows, $enteredBy) {
            $grades = [];

            foreach ($rows as $row) {
                $student = Student::query()->findOrFail($row['student_id']);

                $grades[] = $this->record(
                    $exam,
                    $student,
                    isset($row['mark']) ? (float) $row['mark'] : null,
                    (bool) ($row['is_absent'] ?? false),
                    $row['notes'] ?? null,
                    $enteredBy,
                );
            }

            return $grades;
        });
    }

    /**
     * A student's result sheet: every subject with its weighted average and the
     * overall average, plus pass/fail per subject.
     *
     * @return array<string, mixed>
     */
    public function resultSheet(Student $student, ?int $termId = null): array
    {
        $query = Grade::query()
            ->where('student_id', $student->getKey())
            ->with('exam.subject', 'exam.term');

        if ($termId !== null) {
            $query->whereHas('exam', fn ($q) => $q->where('term_id', $termId));
        }

        $bySubject = $query->get()
            ->filter(fn (Grade $grade) => $grade->exam !== null)
            ->groupBy(fn (Grade $grade) => $grade->exam->subject_id);

        $subjects = [];
        $overallWeight = 0.0;
        $overallPoints = 0.0;

        foreach ($bySubject as $subjectId => $grades) {
            $subject = $grades->first()->exam->subject;
            $weightSum = 0.0;
            $points = 0.0;

            foreach ($grades as $grade) {
                $max = (float) $grade->exam->max_mark ?: 1.0;
                $weight = (float) $grade->exam->weight ?: 1.0;
                $percent = $grade->is_absent || $grade->mark === null
                    ? 0.0
                    : (float) $grade->mark / $max * 100;

                $points += $percent * $weight;
                $weightSum += $weight;
            }

            $average = $weightSum > 0 ? round($points / $weightSum, 2) : 0.0;

            $subjects[] = [
                'subject_id' => $subjectId,
                'subject' => $subject?->name,
                'exams' => $grades->count(),
                'average' => $average,
                'passed' => $average >= (float) ($subject?->pass_mark ?? 50),
            ];

            $overallPoints += $points;
            $overallWeight += $weightSum;
        }

        return [
            'student_id' => $student->getKey(),
            'student_number' => $student->student_number,
            'full_name' => $student->full_name,
            'subjects' => $subjects,
            'overall_average' => $overallWeight > 0 ? round($overallPoints / $overallWeight, 2) : 0.0,
        ];
    }

    /**
     * The whole section's result sheet, ranked by overall average.
     *
     * @return array<int, array<string, mixed>>
     */
    public function sectionResultSheet(int $classSectionId, ?int $termId = null): array
    {
        $students = Student::query()
            ->where('class_section_id', $classSectionId)
            ->where('status', Student::STATUS_ENROLLED)
            ->get();

        $sheets = $students->map(fn (Student $student) => $this->resultSheet($student, $termId))
            ->sortByDesc('overall_average')
            ->values()
            ->all();

        foreach ($sheets as $index => &$sheet) {
            $sheet['rank'] = $index + 1;
        }

        return $sheets;
    }
}

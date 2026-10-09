<?php

namespace App\Services\Reports;

use App\Models\Finance\Invoice;
use App\Models\Organization;
use App\Models\Staff\Teacher;
use App\Models\Student\Student;
use App\Services\Attendance\AttendanceService;
use App\Services\Exams\GradeService;
use Illuminate\Support\Carbon;

/**
 * Builds the general reports (التقارير العامة) as plain header/row arrays so
 * the same definition feeds the JSON API and the PDF/Excel exporters.
 *
 * Each report returns:
 *   ['title' => string, 'headers' => list<string>, 'rows' => list<array>]
 */
class ReportService
{
    public function __construct(
        protected AttendanceService $attendance,
        protected GradeService $grades,
    ) {}

    /**
     * @return array{title: string, headers: list<string>, rows: list<array<string, mixed>>}
     */
    public function build(string $type, array $filters = []): array
    {
        return match ($type) {
            'students' => $this->studentsReport($filters),
            'teachers' => $this->teachersReport($filters),
            'attendance' => $this->attendanceReport($filters),
            'absence-alerts' => $this->absenceAlertsReport($filters),
            'results' => $this->resultsReport($filters),
            'invoices' => $this->invoicesReport($filters),
            default => throw new \InvalidArgumentException("Unknown report type [{$type}]."),
        };
    }

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return ['students', 'teachers', 'attendance', 'absence-alerts', 'results', 'invoices'];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{title: string, headers: list<string>, rows: list<array<string, mixed>>}
     */
    protected function studentsReport(array $filters): array
    {
        $query = Student::query()->with('section.schoolClass')->orderBy('full_name');

        if (! empty($filters['class_section_id'])) {
            $query->where('class_section_id', $filters['class_section_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $rows = $query->get()->map(fn (Student $s) => [
            'student_number' => $s->student_number,
            'full_name' => $s->full_name,
            'gender' => $s->gender === Student::GENDER_FEMALE ? 'أنثى' : 'ذكر',
            'section' => $s->section?->name,
            'status' => $s->status,
            'phone' => $s->phone,
        ])->all();

        return [
            'title' => 'تقرير الطلاب',
            'headers' => ['الرقم الأكاديمي', 'الاسم', 'الجنس', 'الشعبة', 'الحالة', 'الهاتف'],
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{title: string, headers: list<string>, rows: list<array<string, mixed>>}
     */
    protected function teachersReport(array $filters): array
    {
        $rows = Teacher::query()
            ->orderBy('full_name')
            ->get()
            ->map(fn (Teacher $t) => [
                'employee_number' => $t->employee_number,
                'full_name' => $t->full_name,
                'specialization' => $t->specialization,
                'qualification' => $t->qualification,
                'status' => $t->status,
                'phone' => $t->phone,
            ])->all();

        return [
            'title' => 'تقرير المعلمين',
            'headers' => ['الرقم الوظيفي', 'الاسم', 'التخصص', 'المؤهل', 'الحالة', 'الهاتف'],
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{title: string, headers: list<string>, rows: list<array<string, mixed>>}
     */
    protected function attendanceReport(array $filters): array
    {
        $from = $filters['from'] ?? Carbon::now()->startOfMonth()->toDateString();
        $to = $filters['to'] ?? Carbon::now()->toDateString();

        if (empty($filters['class_section_id'])) {
            return [
                'title' => 'تقرير الحضور',
                'headers' => ['الشعبة', 'الطلاب', 'أيام التسجيل'],
                'rows' => [],
            ];
        }

        $rows = $this->attendance->sectionReport((int) $filters['class_section_id'], $from, $to);

        return [
            'title' => 'تقرير الحضور',
            'headers' => ['الرقم الأكاديمي', 'الاسم', 'إجمالي', 'حضور', 'غياب', 'تأخير', 'إذن', 'النسبة %'],
            'rows' => array_map(fn (array $r) => [
                'student_number' => $r['student_number'],
                'full_name' => $r['full_name'],
                'total' => $r['total'],
                'present' => $r['present'],
                'absent' => $r['absent'],
                'late' => $r['late'],
                'excused' => $r['excused'],
                'rate' => $r['rate'],
            ], $rows),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{title: string, headers: list<string>, rows: list<array<string, mixed>>}
     */
    protected function absenceAlertsReport(array $filters): array
    {
        $from = $filters['from'] ?? Carbon::now()->startOfMonth()->toDateString();
        $to = $filters['to'] ?? Carbon::now()->toDateString();
        $threshold = (int) ($filters['threshold'] ?? 3);

        $rows = $this->attendance->absenceAlerts($from, $to, $threshold);

        return [
            'title' => 'تنبيهات الغياب',
            'headers' => ['الرقم الأكاديمي', 'الاسم', 'عدد مرات الغياب'],
            'rows' => array_map(fn (array $r) => [
                'student_number' => $r['student_number'],
                'full_name' => $r['full_name'],
                'absences' => $r['absences'],
            ], $rows),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{title: string, headers: list<string>, rows: list<array<string, mixed>>}
     */
    protected function resultsReport(array $filters): array
    {
        if (empty($filters['class_section_id'])) {
            return [
                'title' => 'تقرير النتائج',
                'headers' => ['الترتيب', 'الرقم الأكاديمي', 'الاسم', 'المعدل العام'],
                'rows' => [],
            ];
        }

        $sheets = $this->grades->sectionResultSheet(
            (int) $filters['class_section_id'],
            $filters['term_id'] ?? null,
        );

        return [
            'title' => 'تقرير النتائج',
            'headers' => ['الترتيب', 'الرقم الأكاديمي', 'الاسم', 'المعدل العام'],
            'rows' => array_map(fn (array $r) => [
                'rank' => $r['rank'],
                'student_number' => $r['student_number'],
                'full_name' => $r['full_name'],
                'overall_average' => $r['overall_average'],
            ], $sheets),
        ];
    }

    /**
     * Outstanding and paid invoices for the school.
     *
     * @param  array<string, mixed>  $filters
     * @return array{title: string, headers: list<string>, rows: list<array<string, mixed>>}
     */
    protected function invoicesReport(array $filters): array
    {
        $query = Invoice::query()
            ->with('student:id,full_name,student_number')
            ->orderByDesc('id');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $rows = $query->get()->map(fn (Invoice $invoice) => [
            'number' => $invoice->number,
            'student_number' => $invoice->student?->student_number,
            'full_name' => $invoice->student?->full_name,
            'net_amount' => (float) $invoice->net_amount,
            'paid_amount' => (float) $invoice->paid_amount,
            'balance' => (float) $invoice->balance,
            'status' => $invoice->status,
            'issued_on' => $invoice->issued_on?->toDateString(),
        ])->all();

        return [
            'title' => 'تقرير الفواتير',
            'headers' => ['رقم الفاتورة', 'الرقم الأكاديمي', 'الاسم', 'الصافي', 'المدفوع', 'المتبقي', 'الحالة', 'تاريخ الإصدار'],
            'rows' => $rows,
        ];
    }

    /**
     * A ministry-level roll-up: counts per school. Platform-wide (no tenant
     * scope) so an authorised ministry user can compare schools.
     *
     * @return array{title: string, headers: list<string>, rows: list<array<string, mixed>>}
     */
    public function schoolsOverview(): array
    {
        $rows = Organization::query()
            ->where('type', Organization::TYPE_SCHOOL)
            ->orderBy('name')
            ->get()
            ->map(fn (Organization $school) => [
                'name' => $school->name,
                'students' => Student::allTenants()->where('tenant_id', $school->getKey())->count(),
                'teachers' => Teacher::allTenants()->where('tenant_id', $school->getKey())->count(),
            ])->all();

        return [
            'title' => 'نظرة عامة على المدارس',
            'headers' => ['المدرسة', 'عدد الطلاب', 'عدد المعلمين'],
            'rows' => $rows,
        ];
    }
}

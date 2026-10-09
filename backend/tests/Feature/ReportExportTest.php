<?php

namespace Tests\Feature;

use App\Models\Academic\SchoolClass;
use App\Models\Academic\Subject;
use App\Models\Exam\Exam;
use App\Models\Organization;
use App\Models\Student\Student;
use App\Services\Exams\GradeService;
use App\Services\Reports\ReportService;
use App\Support\Export\ExcelExporter;
use App\Support\Export\PdfExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    protected function school(): Organization
    {
        $school = Organization::factory()->tenant()->create();
        $this->actingAsTenant($school);

        return $school;
    }

    /**
     * @return array{0: \App\Models\Academic\ClassSection, 1: \App\Models\Academic\Subject, 2: \Illuminate\Support\Collection}
     */
    protected function fixture(): array
    {
        $class = SchoolClass::create(['name' => 'الأول', 'grade' => 1]);
        $section = $class->sections()->create(['name' => 'أ']);
        $subject = Subject::create(['name' => 'الرياضيات', 'code' => 'MATH', 'pass_mark' => 50, 'max_mark' => 100]);

        $students = collect(range(1, 2))->map(fn (int $i) => Student::create([
            'class_section_id' => $section->getKey(),
            'student_number' => 'S-000'.$i,
            'full_name' => 'طالب '.$i,
            'status' => Student::STATUS_ENROLLED,
        ]));

        return [$section, $subject, $students];
    }

    public function test_students_report_has_headers_and_rows(): void
    {
        $this->school();
        $this->fixture();

        $report = app(ReportService::class)->build('students');

        $this->assertSame('تقرير الطلاب', $report['title']);
        $this->assertNotEmpty($report['headers']);
        $this->assertCount(2, $report['rows']);
    }

    public function test_results_report_is_ranked(): void
    {
        $this->school();
        [$section, $subject, $students] = $this->fixture();

        $exam = Exam::create([
            'subject_id' => $subject->getKey(),
            'class_section_id' => $section->getKey(),
            'title' => 'اختبار',
            'max_mark' => 100,
            'pass_mark' => 50,
        ]);

        app(GradeService::class)->recordMany($exam, [
            ['student_id' => $students[0]->id, 'mark' => 95],
            ['student_id' => $students[1]->id, 'mark' => 40],
        ]);

        $report = app(ReportService::class)->build('results', ['class_section_id' => $section->getKey()]);

        $this->assertCount(2, $report['rows']);
        $this->assertSame(1, $report['rows'][0]['rank']);
        $this->assertSame('طالب 1', $report['rows'][0]['full_name']);
    }

    public function test_excel_export_writes_a_valid_xlsx_file(): void
    {
        $path = app(ExcelExporter::class)->temporary(['الاسم', 'الدرجة'], [['علي', 95]], 'النتائج');

        $this->assertFileExists($path);
        $this->assertSame('PK', substr((string) file_get_contents($path), 0, 2)); // xlsx is a zip

        @unlink($path);
    }

    public function test_pdf_export_returns_a_pdf_document(): void
    {
        $pdf = app(PdfExporter::class)->render('<html><body><h1>تقرير</h1></body></html>');

        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_reports_are_isolated_between_schools(): void
    {
        $schoolA = Organization::factory()->tenant()->create();
        $this->actingAsTenant($schoolA);
        $this->fixture();

        $reportA = app(ReportService::class)->build('students');
        $this->assertCount(2, $reportA['rows']);

        $schoolB = Organization::factory()->tenant()->create();
        $this->actingAsTenant($schoolB);

        $reportB = app(ReportService::class)->build('students');
        $this->assertCount(0, $reportB['rows']);
    }
}

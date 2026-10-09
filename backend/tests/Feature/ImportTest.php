<?php

namespace Tests\Feature;

use App\Models\Academic\SchoolClass;
use App\Models\Organization;
use App\Models\Staff\Teacher;
use App\Models\Student\Student;
use App\Services\Import\ImportService;
use App\Support\Export\ExcelExporter;
use App\Support\Import\SpreadsheetReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    protected function school(): Organization
    {
        $school = Organization::factory()->tenant()->create();
        $this->actingAsTenant($school);

        $class = SchoolClass::create(['name' => 'الأول', 'grade' => 1]);
        $class->sections()->create(['name' => 'أ']);

        return $school;
    }

    /**
     * Build a spreadsheet on disk from a header + rows.
     *
     * @param  list<string>  $headers
     * @param  list<array<int, mixed>>  $rows
     */
    protected function spreadsheet(array $headers, array $rows): string
    {
        return app(ExcelExporter::class)->temporary($headers, $rows, 'import');
    }

    public function test_students_are_imported_and_section_is_resolved(): void
    {
        $this->school();

        $path = $this->spreadsheet(
            ['الرقم الأكاديمي', 'الاسم', 'الجنس', 'الصف', 'الشعبة'],
            [
                ['S-1001', 'محمد أحمد', 'ذكر', 'الأول', 'أ'],
                ['S-1002', 'سارة علي', 'أنثى', 'الأول', 'أ'],
            ],
        );

        $summary = app(ImportService::class)->import('students', $path);

        $this->assertSame(2, $summary['created']);
        $this->assertSame(0, $summary['failed']);

        $student = Student::query()->where('student_number', 'S-1001')->first();
        $this->assertSame('محمد أحمد', $student->full_name);
        $this->assertSame(Student::GENDER_MALE, $student->gender);
        $this->assertNotNull($student->class_section_id);
        $this->assertSame(2, Student::query()->count());
    }

    public function test_reimport_updates_instead_of_duplicating(): void
    {
        $this->school();

        $path = $this->spreadsheet(
            ['الرقم الأكاديمي', 'الاسم'],
            [['S-1001', 'الاسم القديم']],
        );

        app(ImportService::class)->import('students', $path);

        $updated = $this->spreadsheet(
            ['الرقم الأكاديمي', 'الاسم'],
            [['S-1001', 'الاسم الجديد']],
        );

        $summary = app(ImportService::class)->import('students', $updated);

        $this->assertSame(1, $summary['updated']);
        $this->assertSame(1, Student::query()->count());
        $this->assertSame('الاسم الجديد', Student::query()->first()->full_name);
    }

    public function test_bad_rows_are_reported_without_discarding_valid_rows(): void
    {
        $this->school();

        $path = $this->spreadsheet(
            ['الرقم الأكاديمي', 'الاسم', 'الصف', 'الشعبة'],
            [
                ['S-1', 'طالب صحيح', 'الأول', 'أ'],
                ['', 'بلا رقم', 'الأول', 'أ'],            // missing required number
                ['S-3', 'شعبة خاطئة', 'الخامس', 'ب'],      // unknown section
                ['S-4', 'طالب صحيح آخر', 'الأول', 'أ'],
            ],
        );

        $summary = app(ImportService::class)->import('students', $path);

        $this->assertSame(4, $summary['total']);
        $this->assertSame(2, $summary['created']);
        $this->assertSame(2, $summary['failed']);
        $this->assertCount(2, $summary['errors']);
        $this->assertSame(2, Student::query()->count());
    }

    public function test_teachers_are_imported_by_employee_number(): void
    {
        $this->school();

        $path = $this->spreadsheet(
            ['الرقم الوظيفي', 'الاسم', 'التخصص', 'البريد'],
            [
                ['T-2001', 'علي صالح', 'رياضيات', 'ali@example.com'],
                ['T-2002', 'منى حسن', 'لغة عربية', ''],
            ],
        );

        $summary = app(ImportService::class)->import('teachers', $path);

        $this->assertSame(2, $summary['created']);
        $this->assertSame(2, Teacher::query()->count());
        $this->assertSame('رياضيات', Teacher::query()->first()->specialization);
    }

    public function test_template_sample_round_trips_through_the_reader(): void
    {
        $this->assertContains('students', ImportService::types());

        $template = ImportService::templates()['students'];
        $path = $this->spreadsheet($template['headers'], [array_values($template['sample'])]);
        $parsed = (new SpreadsheetReader)->read($path);

        $this->assertSame($template['headers'], $parsed['headers']);
        $this->assertCount(1, $parsed['rows']);
        $this->assertSame('محمد أحمد', $parsed['rows'][0]['الاسم']);
    }
}

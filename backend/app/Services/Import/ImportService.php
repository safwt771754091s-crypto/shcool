<?php

namespace App\Services\Import;

use App\Models\Academic\ClassSection;
use App\Models\Staff\Teacher;
use App\Models\Student\Student;
use App\Support\Import\SpreadsheetReader;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Bulk data migration from Excel/CSV templates (ترحيل البيانات عبر قوالب Excel).
 *
 * Each row is applied inside its own savepoint, so one malformed row is
 * reported and skipped without discarding the valid rows in the same file.
 * Import is idempotent on the natural key (student number / employee number):
 * re-uploading a corrected file updates rather than duplicates.
 */
class ImportService
{
    public function __construct(protected SpreadsheetReader $reader) {}

    /**
     * The available import types with their expected Arabic headers and a
     * sample row, used both to render the template download and to validate.
     *
     * @return array<string, array{label: string, headers: list<string>, sample: array<string, string>}>
     */
    public static function templates(): array
    {
        return [
            'students' => [
                'label' => 'الطلاب',
                'headers' => ['الرقم الأكاديمي', 'الاسم', 'الجنس', 'تاريخ الميلاد', 'الرقم الوطني', 'الهاتف', 'العنوان', 'الصف', 'الشعبة'],
                'sample' => [
                    'الرقم الأكاديمي' => 'S-1001',
                    'الاسم' => 'محمد أحمد',
                    'الجنس' => 'ذكر',
                    'تاريخ الميلاد' => '2012-05-10',
                    'الرقم الوطني' => '123456789',
                    'الهاتف' => '0770000000',
                    'العنوان' => 'صنعاء',
                    'الصف' => 'الأول',
                    'الشعبة' => 'أ',
                ],
            ],
            'teachers' => [
                'label' => 'المعلمون',
                'headers' => ['الرقم الوظيفي', 'الاسم', 'الجنس', 'الهاتف', 'البريد', 'التخصص', 'المؤهل', 'المسمى الوظيفي'],
                'sample' => [
                    'الرقم الوظيفي' => 'T-2001',
                    'الاسم' => 'علي صالح',
                    'الجنس' => 'ذكر',
                    'الهاتف' => '0771111111',
                    'البريد' => 'ali@example.com',
                    'التخصص' => 'رياضيات',
                    'المؤهل' => 'بكالوريوس',
                    'المسمى الوظيفي' => 'معلم',
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return array_keys(self::templates());
    }

    /**
     * Import a file for the given type.
     *
     * @return array{type: string, total: int, created: int, updated: int, failed: int, errors: list<array{row: int, message: string}>}
     */
    public function import(string $type, string $path): array
    {
        if (! array_key_exists($type, self::templates())) {
            throw new \InvalidArgumentException("Unknown import type [{$type}].");
        }

        $parsed = $this->reader->read($path);

        $summary = [
            'type' => $type,
            'total' => count($parsed['rows']),
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($parsed['rows'] as $index => $row) {
            $line = $index + 2; // 1-based, header is line 1.

            try {
                $result = DB::transaction(fn () => $this->apply($type, $row));

                $summary[$result ? 'updated' : 'created']++;
            } catch (\Throwable $e) {
                $summary['failed']++;
                $summary['errors'][] = ['row' => $line, 'message' => $e->getMessage()];
            }
        }

        return $summary;
    }

    /**
     * @param  array<string, string>  $row
     * @return bool true when an existing record was updated
     */
    protected function apply(string $type, array $row): bool
    {
        return match ($type) {
            'students' => $this->applyStudent($row),
            'teachers' => $this->applyTeacher($row),
        };
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function applyStudent(array $row): bool
    {
        $number = $this->require($row, 'الرقم الأكاديمي');
        $name = $this->require($row, 'الاسم');

        $attributes = [
            'full_name' => $name,
            'gender' => $this->gender($row['الجنس'] ?? null),
            'birth_date' => $this->date($row['تاريخ الميلاد'] ?? null),
            'national_id' => $this->nullable($row['الرقم الوطني'] ?? null),
            'phone' => $this->nullable($row['الهاتف'] ?? null),
            'address' => $this->nullable($row['العنوان'] ?? null),
            'class_section_id' => $this->resolveSection($row['الصف'] ?? null, $row['الشعبة'] ?? null),
            'status' => Student::STATUS_ENROLLED,
        ];

        $existing = Student::query()->where('student_number', $number)->first();

        if ($existing) {
            $existing->update($attributes);

            return true;
        }

        Student::create(array_merge($attributes, ['student_number' => $number]));

        return false;
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function applyTeacher(array $row): bool
    {
        $number = $this->require($row, 'الرقم الوظيفي');
        $name = $this->require($row, 'الاسم');

        $attributes = [
            'full_name' => $name,
            'gender' => $this->gender($row['الجنس'] ?? null),
            'phone' => $this->nullable($row['الهاتف'] ?? null),
            'email' => $this->nullable($row['البريد'] ?? null),
            'specialization' => $this->nullable($row['التخصص'] ?? null),
            'qualification' => $this->nullable($row['المؤهل'] ?? null),
            'job_title' => $this->nullable($row['المسمى الوظيفي'] ?? null),
            'status' => Teacher::STATUS_ACTIVE,
        ];

        $existing = Teacher::query()->where('employee_number', $number)->first();

        if ($existing) {
            $existing->update($attributes);

            return true;
        }

        Teacher::create(array_merge($attributes, ['employee_number' => $number]));

        return false;
    }

    /**
     * Resolve a class level + section name pair to a section id inside the
     * current school, tolerating a numeric id in the section column.
     */
    protected function resolveSection(?string $className, ?string $sectionName): ?int
    {
        $className = $this->nullable($className);
        $sectionName = $this->nullable($sectionName);

        if ($className === null && $sectionName === null) {
            return null;
        }

        $query = ClassSection::query()->with('schoolClass');

        if ($className !== null) {
            $query->whereHas('schoolClass', fn ($q) => $q->where('name', $className));
        }

        if ($sectionName !== null) {
            $query->where('name', $sectionName);
        }

        $section = $query->first();

        if ($section === null && $className !== null && $sectionName !== null) {
            throw new \RuntimeException("الشعبة [{$className} / {$sectionName}] غير موجودة.");
        }

        return $section?->getKey();
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function require(array $row, string $column): string
    {
        $value = $this->nullable($row[$column] ?? null);

        if ($value === null) {
            throw new \RuntimeException("الحقل [{$column}] مطلوب.");
        }

        return $value;
    }

    protected function nullable(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }

    protected function gender(?string $value): ?string
    {
        return match (trim((string) $value)) {
            'ذكر', 'male', 'M' => Student::GENDER_MALE,
            'أنثى', 'انثى', 'female', 'F' => Student::GENDER_FEMALE,
            default => null,
        };
    }

    protected function date(?string $value): ?string
    {
        $value = $this->nullable($value);

        if ($value === null) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            throw new \RuntimeException("صيغة التاريخ غير صحيحة: {$value}.");
        }
    }
}

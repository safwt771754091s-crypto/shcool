<?php

namespace Database\Seeders;

use App\Models\Academic\AcademicYear;
use App\Models\Academic\ClassSection;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Subject;
use App\Models\Academic\Term;
use App\Models\Organization;
use App\Support\Tenancy\TenantManager;
use Illuminate\Database\Seeder;

/**
 * Seeds the academic year, subjects, classes and sections for the sample
 * school. Runs inside the school's tenant so every row is isolated.
 */
class AcademicStructureSeeder extends Seeder
{
    public function run(TenantManager $tenants): void
    {
        $school = Organization::query()->where('type', Organization::TYPE_SCHOOL)->first();

        if (! $school) {
            return;
        }

        $tenants->setTenant($school);

        $year = AcademicYear::create([
            'name' => '2025-2026',
            'starts_on' => now()->startOfYear()->toDateString(),
            'ends_on' => now()->endOfYear()->toDateString(),
            'is_current' => true,
        ]);

        $term = $year->terms()->create([
            'name' => 'الفصل الأول',
            'sequence' => 1,
            'starts_on' => now()->startOfYear()->toDateString(),
            'ends_on' => now()->startOfYear()->addMonths(4)->toDateString(),
            'is_current' => true,
        ]);

        foreach ([
            ['name' => 'الرياضيات', 'code' => 'MATH'],
            ['name' => 'اللغة العربية', 'code' => 'ARB'],
            ['name' => 'اللغة الإنجليزية', 'code' => 'ENG'],
            ['name' => 'العلوم', 'code' => 'SCI'],
        ] as $subject) {
            Subject::create($subject + ['stage' => 'primary']);
        }

        $branch = Organization::query()
            ->where('type', Organization::TYPE_BRANCH)
            ->where('tenant_id', $school->getKey())
            ->first();

        foreach ([1, 2] as $grade) {
            $class = SchoolClass::create([
                'branch_id' => $branch?->getKey(),
                'name' => 'الصف '.$grade.' الابتدائي',
                'grade' => $grade,
                'stage' => 'primary',
            ]);

            foreach (['أ', 'ب'] as $name) {
                $class->sections()->create(['name' => $name, 'capacity' => 30]);
            }
        }

        $this->command?->info('Academic year, terms, subjects, classes and sections seeded.');
    }
}

<?php

namespace Database\Seeders;

use App\Models\Academic\Subject;
use App\Models\Academic\Term;
use App\Models\Organization;
use App\Models\Teaching\CurriculumUnit;
use App\Support\Tenancy\TenantManager;
use Illuminate\Database\Seeder;

/**
 * Seeds a sample curriculum (units -> lessons) for each subject so the
 * preparation notebook has something to reference.
 */
class TeachingContentSeeder extends Seeder
{
    public function run(TenantManager $tenants): void
    {
        $school = Organization::query()->where('type', Organization::TYPE_SCHOOL)->first();

        if (! $school) {
            return;
        }

        $tenants->setTenant($school);

        $term = Term::query()->orderBy('sequence')->first();

        foreach (Subject::query()->get() as $subject) {
            $unit = CurriculumUnit::create([
                'subject_id' => $subject->getKey(),
                'term_id' => $term?->getKey(),
                'name' => 'الوحدة الأولى - '.$subject->name,
                'sequence' => 1,
            ]);

            foreach (['الدرس الأول', 'الدرس الثاني', 'الدرس الثالث'] as $index => $title) {
                $unit->lessons()->create([
                    'title' => $title.' - '.$subject->name,
                    'sequence' => $index + 1,
                    'planned_periods' => 2,
                    'objectives' => 'أن يتعرّف الطالب على مفاهيم '.$title.'.',
                ]);
            }
        }

        $this->command?->info('Curriculum units and lessons seeded.');
    }
}

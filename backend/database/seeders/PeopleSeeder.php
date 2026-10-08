<?php

namespace Database\Seeders;

use App\Models\Academic\ClassSection;
use App\Models\Academic\Subject;
use App\Models\Organization;
use App\Models\Staff\Teacher;
use App\Models\Staff\TeachingAssignment;
use App\Models\Student\Guardian;
use App\Models\Student\Student;
use App\Models\User;
use App\Support\Permission\Roles;
use App\Support\Tenancy\TenantManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds teachers, students and guardians for the sample school, with logins
 * for one teacher, one student and one parent so each portal can be exercised.
 */
class PeopleSeeder extends Seeder
{
    public function run(TenantManager $tenants, PermissionRegistrar $registrar): void
    {
        $school = Organization::query()->where('type', Organization::TYPE_SCHOOL)->first();

        if (! $school) {
            return;
        }

        $tenants->setTenant($school);
        $registrar->setPermissionsTeamId($school->getKey());

        $sections = ClassSection::query()->orderBy('id')->get();
        $subjects = Subject::query()->orderBy('id')->get();

        if ($sections->isEmpty() || $subjects->isEmpty()) {
            return;
        }

        $this->teachers($school, $sections, $subjects);
        $this->students($school, $sections);
    }

    protected function teachers(Organization $school, $sections, $subjects): void
    {
        $names = ['أحمد الجبوري', 'سارة العبيدي', 'محمد الكناني', 'ليلى الحسيني'];

        foreach ($names as $index => $name) {
            $user = User::factory()->create([
                'name' => $name,
                'email' => 'teacher'.($index + 1).'@school-platform.local',
                'password' => Hash::make('password'),
                'tenant_id' => $school->getKey(),
            ]);
            $user->assignRole(Roles::TEACHER);

            $teacher = Teacher::create([
                'user_id' => $user->getKey(),
                'employee_number' => 'T-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'full_name' => $name,
                'specialization' => $subjects[$index % $subjects->count()]->name,
                'job_title' => 'معلم',
                'status' => Teacher::STATUS_ACTIVE,
            ]);

            // One assignment per subject/section pair for this teacher.
            TeachingAssignment::updateOrCreate(
                [
                    'teacher_id' => $teacher->getKey(),
                    'subject_id' => $subjects[$index % $subjects->count()]->getKey(),
                    'class_section_id' => $sections[$index % $sections->count()]->getKey(),
                    'academic_year_id' => null,
                ],
                ['weekly_periods' => 5, 'is_active' => true],
            );
        }
    }

    protected function students(Organization $school, $sections): void
    {
        $guardianUser = User::factory()->create([
            'name' => 'ولي أمر تجريبي',
            'email' => 'parent@school-platform.local',
            'password' => Hash::make('password'),
            'tenant_id' => $school->getKey(),
        ]);
        $guardianUser->assignRole(Roles::PARENT);

        $guardian = Guardian::create([
            'user_id' => $guardianUser->getKey(),
            'full_name' => 'ولي أمر تجريبي',
            'relation' => Guardian::RELATION_FATHER,
            'phone' => '07700000000',
        ]);

        $names = ['علي محمد', 'فاطمة أحمد', 'يوسف حسين', 'زينب كريم', 'حسن عادل', 'مريم سعد'];

        foreach ($names as $index => $name) {
            $section = $sections[$index % $sections->count()];

            $student = Student::create([
                'class_section_id' => $section->getKey(),
                'student_number' => 'S-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'full_name' => $name,
                'gender' => $index % 2 === 0 ? Student::GENDER_MALE : Student::GENDER_FEMALE,
                'status' => Student::STATUS_ENROLLED,
                'admitted_at' => now()->toDateString(),
            ]);

            // The first two students are siblings of the sample guardian.
            if ($index < 2) {
                $student->guardians()->attach($guardian->getKey(), [
                    'tenant_id' => $school->getKey(),
                    'relation' => Guardian::RELATION_FATHER,
                    'is_primary' => true,
                ]);
            }
        }

        // Give the first student a login so the student portal can be exercised.
        $first = Student::query()->orderBy('id')->first();
        if ($first !== null) {
            $studentUser = User::factory()->create([
                'name' => $first->full_name,
                'email' => 'student@school-platform.local',
                'password' => Hash::make('password'),
                'tenant_id' => $school->getKey(),
            ]);
            $studentUser->assignRole(Roles::STUDENT);
            $first->forceFill(['user_id' => $studentUser->getKey()])->save();
        }

        $this->command?->info('Teachers, students and guardians seeded.');
    }
}

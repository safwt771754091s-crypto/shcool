<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use App\Support\Permission\GlobalTeam;
use App\Support\Permission\Roles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class PlatformAdminSeeder extends Seeder
{
    public function run(PermissionRegistrar $registrar): void
    {
        // Global roles live in the reserved global team.
        $registrar->setPermissionsTeamId(GlobalTeam::ID);

        $owner = User::query()->updateOrCreate(
            ['email' => 'owner@school-platform.local'],
            [
                'name' => 'المهندس صفوت البريهي',
                'password' => Hash::make('password'),
                'is_active' => true,
                'is_platform_admin' => true,
                'email_verified_at' => now(),
            ],
        );

        $owner->assignRole(Roles::OWNER);

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@school-platform.local'],
            [
                'name' => 'مدير المنصة',
                'password' => Hash::make('password'),
                'is_active' => true,
                'is_platform_admin' => true,
                'email_verified_at' => now(),
            ],
        );

        $admin->assignRole(Roles::SUPER_ADMIN);

        // The Minister of Education: a read-only, platform-wide monitoring
        // account. Platform admin so the tenant is resolved to the global team
        // (whole-country scope); the `minister` role grants only read-only
        // permissions plus `platform.monitor`.
        $minister = User::query()->updateOrCreate(
            ['email' => 'minister@school-platform.local'],
            [
                'name' => 'وزير التربية',
                'password' => Hash::make('password'),
                'is_active' => true,
                'is_platform_admin' => true,
                'email_verified_at' => now(),
            ],
        );

        $minister->assignRole(Roles::MINISTER);

        // A sample school manager inside the seeded school.
        $school = Organization::query()->where('type', Organization::TYPE_SCHOOL)->first();

        if ($school) {
            $registrar->setPermissionsTeamId($school->getKey());

            $manager = User::query()->updateOrCreate(
                ['email' => 'manager@school-platform.local'],
                [
                    'name' => 'مدير المدرسة',
                    'password' => Hash::make('password'),
                    'tenant_id' => $school->getKey(),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );

            $manager->assignRole(Roles::SCHOOL_MANAGER);
            $manager->organizations()->syncWithoutDetaching([$school->getKey() => ['is_primary' => true]]);
        }

        $registrar->setPermissionsTeamId(GlobalTeam::ID);

        $this->command?->info('Platform owner, admin and sample school manager created.');
    }
}

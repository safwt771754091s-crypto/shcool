<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            OrganizationSeeder::class,
            AcademicStructureSeeder::class,
            TeachingContentSeeder::class,
            CompetitionSeeder::class,
            PlatformAdminSeeder::class,
            SportsLeagueSeeder::class,
            InteractiveActivitySeeder::class,
            MiniAppSeeder::class,
            PeopleSeeder::class,
            AcademicRecordsSeeder::class,
        ]);
    }
}

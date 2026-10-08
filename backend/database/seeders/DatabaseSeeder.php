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
            CompetitionSeeder::class,
            PlatformAdminSeeder::class,
        ]);
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Institute;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserTableSeeder extends Seeder
{
    public function run(): void
    {
        $users = DB::table('users');
        if ($users->count() == 0) {
            User::create([
                'firstname' => 'Mr.', 'lastname' => 'Admin', 'login' => 'admin',
                'email' => 'admin@school.dev', 'group' => 'Admin',
                'desc' => 'Admin Details Here', 'password' => Hash::make('123456'),
            ]);
            User::create([
                'firstname' => 'Mr.', 'lastname' => 'Other', 'login' => 'other',
                'email' => 'other@school.dev', 'group' => 'Other',
                'desc' => 'Other Details Here', 'password' => Hash::make('123456'),
            ]);
        }

        if (Institute::count() == 0) {
            Institute::create([
                'name' => 'حقيبة المعلم الرقمية المتميزة',
                'establish' => date('Y'),
                'email' => 'admin@example.com',
                'web' => '',
                'phoneNo' => '',
                'address' => '',
            ]);
        }

        $basePath = base_path('sql');
        $files = [
            'student.sql' => 'Student table seeded!',
            'class.sql' => 'class table seeded!',
            'section.sql' => 'section table seeded!',
            'subjects.sql' => 'subject table seeded!',
            'marks.sql' => 'marks table seeded!',
            'grade.sql' => 'grade table seeded!',
            'teacher.sql' => 'teacher table seeded!',
        ];

        foreach ($files as $file => $message) {
            $path = $basePath . DIRECTORY_SEPARATOR . $file;
            if (!is_file($path)) {
                $this->command->warn("Seeder SQL file missing: {$path}");
                continue;
            }
            DB::unprepared(file_get_contents($path));
            $this->command->info($message);
        }
    }
}

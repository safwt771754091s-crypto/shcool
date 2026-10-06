<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration {
    public function up(): void
    {
        $login = env('OWNER_LOGIN');
        $email = env('OWNER_EMAIL');
        $password = env('OWNER_PASSWORD');

        if (!$login || !$email || !$password) return;

        $owner = DB::table('users')->where('login', $login)->first();

        if ($owner) {
            DB::table('users')->where('id', $owner->id)->update([
                'email' => $email,
                'group' => 'Admin',
                'password' => Hash::make($password),
                'firstname' => env('OWNER_FIRSTNAME', 'مالك'),
                'lastname' => env('OWNER_LASTNAME', 'المنصة'),
                'desc' => 'Platform Owner',
            ]);
            return;
        }

        $admin = DB::table('users')->where('group', 'Admin')->orderBy('id')->first();

        if ($admin) {
            DB::table('users')->where('id', $admin->id)->update([
                'login' => $login,
                'email' => $email,
                'group' => 'Admin',
                'password' => Hash::make($password),
                'firstname' => env('OWNER_FIRSTNAME', 'مالك'),
                'lastname' => env('OWNER_LASTNAME', 'المنصة'),
                'desc' => 'Platform Owner',
            ]);
            return;
        }

        DB::table('users')->insert([
            'firstname' => env('OWNER_FIRSTNAME', 'مالك'),
            'lastname' => env('OWNER_LASTNAME', 'المنصة'),
            'login' => $login,
            'email' => $email,
            'group' => 'Admin',
            'desc' => 'Platform Owner',
            'password' => Hash::make($password),
        ]);
    }

    public function down(): void {}
};
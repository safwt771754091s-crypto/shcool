<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration {
    public function up(): void
    {
        $login = env('OWNER_LOGIN');
        $email = env('OWNER_EMAIL');
        $password = env('OWNER_PASSWORD');

        if (!$login || !$email || !$password) {
            return;
        }

        if (DB::table('users')->where('group','Admin')->exists()) {
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
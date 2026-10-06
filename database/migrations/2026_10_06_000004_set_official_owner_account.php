<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $officialEmail = 'Safwt771754091s@gmail.com';
        $oldEmail = 'owner@hqeebat-almoalem.com';

        DB::table('users')
            ->where('email', $oldEmail)
            ->where('email', '!=', $officialEmail)
            ->delete();

        $owner = DB::table('users')->where('email', $officialEmail)->first();

        if (!$owner) {
            $owner = DB::table('users')->where('login', 'owner')->first();
        }

        if ($owner) {
            // login is a short internal identifier; email is the official identity.
            DB::table('users')->where('id', $owner->id)->update([
                'login' => 'owner',
                'email' => $officialEmail,
                'group' => 'Admin',
                'desc' => 'Platform Owner',
            ]);

            DB::table('users')
                ->where('email', $officialEmail)
                ->where('id', '!=', $owner->id)
                ->delete();
        }

        DB::table('users')
            ->where('login', 'owner')
            ->where('id', '!=', $owner?->id ?? 0)
            ->delete();
    }

    public function down(): void
    {
        // Intentionally irreversible: the temporary owner identity is retired.
    }
};

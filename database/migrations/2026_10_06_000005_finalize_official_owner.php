<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $officialEmail = 'Safwt771754091s@gmail.com';
        $oldEmail = 'owner@hqeebat-almoalem.com';
        $owner = DB::table('users')->where('email', $officialEmail)->first();

        // Remove the retired temporary owner account.
        DB::table('users')
            ->where('email', $oldEmail)
            ->where('email', '!=', $officialEmail)
            ->delete();

        // The database login column is intentionally kept as the short "owner"
        // identifier; the official email is the account's email identity.
        if (!$owner) {
            $owner = DB::table('users')->where('login', 'owner')->first();
        }

        if ($owner) {
            DB::table('users')->where('id', $owner->id)->update([
                'login' => 'owner',
                'email' => $officialEmail,
                'group' => 'Admin',
                'desc' => 'Platform Owner',
            ]);
        }
    }

    public function down(): void
    {
        // Intentionally irreversible: the retired temporary owner is not restored.
    }
};

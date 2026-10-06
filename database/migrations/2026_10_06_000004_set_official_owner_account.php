<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $officialEmail = 'Safwt771754091s@gmail.com';
        $oldEmail = 'owner@hqeebat-almoalem.com';

        // Remove the temporary owner account if it is not the official account.
        DB::table('users')
            ->where('email', $oldEmail)
            ->where('email', '!=', $officialEmail)
            ->delete();

        // Promote/reconcile the official account as the single platform owner.
        $owner = DB::table('users')->where('email', $officialEmail)->first();

        if (!$owner) {
            $owner = DB::table('users')->where('login', 'owner')->first();
        }

        if ($owner) {
            DB::table('users')->where('id', $owner->id)->update([
                'login' => $officialEmail,
                'email' => $officialEmail,
                'group' => 'Admin',
                'desc' => 'Platform Owner',
            ]);
        }

        // Remove any accidental duplicate owner login/email records, preserving the official account.
        DB::table('users')
            ->where('email', $officialEmail)
            ->where('id', '!=', $owner?->id ?? 0)
            ->delete();

        DB::table('users')
            ->where('login', 'owner')
            ->where('email', '!=', $officialEmail)
            ->delete();
    }

    public function down(): void
    {
        // Intentionally irreversible: the temporary owner identity is retired.
    }
};

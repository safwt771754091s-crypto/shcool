<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // The school the account belongs to. Null for platform staff.
            $table->foreignId('tenant_id')
                ->nullable()
                ->after('id')
                ->constrained('organizations')
                ->nullOnDelete();

            $table->string('phone', 30)->nullable()->unique()->after('email');
            $table->string('national_id', 50)->nullable()->unique()->after('phone');
            $table->string('avatar')->nullable()->after('national_id');

            $table->boolean('is_active')->default(true)->after('avatar');
            $table->boolean('is_platform_admin')->default(false)->after('is_active');

            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();

            // Two-factor authentication (Google Authenticator / TOTP).
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();

            $table->softDeletes();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn([
                'phone',
                'national_id',
                'avatar',
                'is_active',
                'is_platform_admin',
                'last_login_at',
                'last_login_ip',
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
                'deleted_at',
            ]);
        });
    }
};

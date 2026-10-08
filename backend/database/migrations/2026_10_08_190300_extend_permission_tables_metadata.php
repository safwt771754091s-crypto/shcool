<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('permission.table_names.roles'), function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('name');
            $table->unsignedTinyInteger('level')->default(4)->after('display_name');
            $table->boolean('is_system')->default(false)->after('level');
        });

        Schema::table(config('permission.table_names.permissions'), function (Blueprint $table) {
            $table->string('module', 50)->nullable()->after('name');
            $table->string('display_name')->nullable()->after('module');
        });

        // Convenience index for "list roles of a school".
        Schema::table(config('permission.table_names.roles'), function (Blueprint $table) {
            $table->index(['tenant_id', 'level'], 'roles_tenant_level_index');
        });
    }

    public function down(): void
    {
        Schema::table(config('permission.table_names.roles'), function (Blueprint $table) {
            $table->dropIndex('roles_tenant_level_index');
            $table->dropColumn(['display_name', 'level', 'is_system']);
        });

        Schema::table(config('permission.table_names.permissions'), function (Blueprint $table) {
            $table->dropColumn(['module', 'display_name']);
        });
    }
};

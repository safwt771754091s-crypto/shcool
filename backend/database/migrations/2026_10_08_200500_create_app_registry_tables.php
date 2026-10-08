<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * App registry (سجل التطبيقات المصغّرة).
 *
 * Each school has its own mini-app; all schools of a directorate roll up into
 * a directorate app; all directorates of a governorate roll up into a
 * governorate app; and the platform aggregates them all.
 *
 * Instead of one row per organization we store a *template* plus a
 * materialised catalogue: `mini_apps` describes the app, and `mini_app_instances`
 * records which organization it is published to and how it is configured.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mini_apps', function (Blueprint $table) {
            $table->id();

            // Null = a platform-wide template maintained by the ministry.
            $table->foreignId('tenant_id')
                ->nullable()
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('slug', 80);
            $table->string('category', 40)->nullable();  // teaching | sports | admin | ...
            $table->text('description')->nullable();
            $table->string('icon', 60)->nullable();
            $table->string('color', 20)->nullable();

            // Which organization levels may install it.
            $table->json('supported_scopes')->nullable();

            $table->boolean('is_published')->default(false);

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'is_published']);
        });

        Schema::create('mini_app_instances', function (Blueprint $table) {
            $table->id();

            // Null for instances published to organizations above school level.
            $table->foreignId('tenant_id')
                ->nullable()
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('mini_app_id')
                ->constrained('mini_apps')
                ->cascadeOnDelete();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            // school | directorate | governorate | ministry
            $table->string('scope_type', 20);

            $table->json('settings')->nullable();
            $table->boolean('is_enabled')->default(true);

            $table->timestamps();

            $table->unique(
                ['mini_app_id', 'organization_id'],
                'mini_app_instances_unique_org'
            );
            $table->index(['organization_id', 'scope_type']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mini_app_instances');
        Schema::dropIfExists('mini_apps');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gamification: badges and awards earned by entities, plus their point value.
 * Achievements can be defined globally and awarded per tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();

            $table->string('key', 60)->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('color', 20)->nullable();

            $table->integer('points')->default(0);
            $table->string('scope_type', 20)->default('student');

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });

        Schema::create('achievement_awards', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->nullable()
                ->constrained('organizations')
                ->nullOnDelete();

            $table->foreignId('achievement_id')
                ->constrained('achievements')
                ->cascadeOnDelete();

            $table->string('awardable_type');
            $table->unsignedBigInteger('awardable_id');

            $table->foreignId('awarded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('reason')->nullable();
            $table->timestamp('awarded_at');

            $table->timestamps();

            $table->index(['awardable_type', 'awardable_id']);
            $table->index(['tenant_id', 'awarded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('achievement_awards');
        Schema::dropIfExists('achievements');
    }
};

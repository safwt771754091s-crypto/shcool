<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The scoring dimensions of the competition. Each metric has a weight so the
 * platform can tune what "excellence" means (e.g. grades 50%, attendance 30%,
 * behaviour 20%). Metrics are global definitions, not tenant data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_metrics', function (Blueprint $table) {
            $table->id();

            $table->string('key', 60)->unique();
            $table->string('name');
            $table->string('description')->nullable();

            // How the raw value maps to points: higher | lower
            $table->string('direction', 10)->default('higher');

            // Relative weight used when computing the composite score.
            $table->decimal('weight', 5, 2)->default(1);

            // The scopes this metric contributes to (student, class, school, ...).
            $table->json('scopes')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_metrics');
    }
};

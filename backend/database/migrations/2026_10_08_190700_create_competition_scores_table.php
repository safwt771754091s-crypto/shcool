<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A single measured value for one entity in one period for one metric.
 *
 * The entity is polymorphic so the same table holds scores for a student, a
 * class, a school, a directorate, a governorate or the ministry. Each row is
 * tenant-scoped so a school only ever sees and writes its own rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_scores', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('ranking_period_id')
                ->constrained('ranking_periods')
                ->cascadeOnDelete();

            $table->foreignId('competition_metric_id')
                ->constrained('competition_metrics')
                ->cascadeOnDelete();

            $table->string('scorable_type');
            $table->unsignedBigInteger('scorable_id');

            // The measured value (e.g. 92.5 for an average, 0.97 for a rate).
            $table->decimal('raw_value', 12, 4)->default(0);
            // The weighted points derived from raw_value.
            $table->decimal('points', 12, 4)->default(0);

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(
                ['ranking_period_id', 'competition_metric_id', 'scorable_type', 'scorable_id'],
                'competition_scores_unique_entity_metric'
            );
            $table->index(['tenant_id', 'ranking_period_id']);
            $table->index(['scorable_type', 'scorable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_scores');
    }
};

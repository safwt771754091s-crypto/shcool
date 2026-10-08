<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Materialised ranking rows produced by the aggregation engine.
 *
 * Competition scores are raw; leaderboard entries are the ranked result per
 * scope per period. Keeping them materialised lets the platform serve the
 * ministry/governorate/directorate dashboards instantly without recomputing.
 *
 * `scope_type` mirrors the organization level: student | class | school |
 * directorate | governorate | ministry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leaderboard_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ranking_period_id')
                ->constrained('ranking_periods')
                ->cascadeOnDelete();

            $table->string('scope_type', 20);
            $table->unsignedBigInteger('scope_id')->nullable();

            // Denormalised tenant for fast filtering; null for cross-school scopes.
            $table->foreignId('tenant_id')
                ->nullable()
                ->constrained('organizations')
                ->nullOnDelete();

            $table->string('name');

            $table->decimal('total_points', 12, 4)->default(0);
            $table->unsignedInteger('rank')->nullable();
            $table->unsignedInteger('previous_rank')->nullable();
            $table->integer('rank_delta')->default(0);

            // Number of entities this row aggregates (e.g. students in a school).
            $table->unsignedInteger('sample_size')->default(0);

            $table->json('breakdown')->nullable();
            $table->timestamp('computed_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['ranking_period_id', 'scope_type', 'scope_id'],
                'leaderboard_entries_unique_scope'
            );
            $table->index(['scope_type', 'ranking_period_id', 'rank']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leaderboard_entries');
    }
};

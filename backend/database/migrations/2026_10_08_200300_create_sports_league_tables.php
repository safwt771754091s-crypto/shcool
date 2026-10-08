<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sports league (الدوري الرياضي) with elimination brackets.
 *
 * A competition runs across the whole hierarchy. Matches are first played
 * between sections of the same school, then between classes, then between
 * schools, then between directorate offices (مراكز التربية), then between
 * governorates, and finally the ministry final.
 *
 * Each match stores a link to the match it feeds (next_match_id), so the
 * bracket can be rendered and advanced without extra bookkeeping.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sport_competitions', function (Blueprint $table) {
            $table->id();

            // Null = platform-wide (ministry) competition.
            $table->foreignId('tenant_id')
                ->nullable()
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->string('name');                     // "دوري كرة القدم الابتدائي"
            $table->string('sport', 40);                // football | volleyball | ...
            $table->string('stage', 20)->default('primary');
            $table->string('age_group', 20)->nullable();

            // section | class | school | directorate | governorate | ministry
            $table->string('current_scope', 20)->default('section');

            // draft | group_stage | knockout | finished | cancelled
            $table->string('status', 20)->default('draft');

            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();

            $table->foreignId('organizer_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['sport', 'current_scope']);
        });

        Schema::create('sport_participants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->nullable()
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('sport_competition_id')
                ->constrained('sport_competitions')
                ->cascadeOnDelete();

            // section | class | school | directorate | governorate | ministry
            $table->string('scope_type', 20);
            $table->unsignedBigInteger('scope_id')->nullable();

            $table->string('name');
            $table->string('coach', 120)->nullable();
            $table->unsignedTinyInteger('seed')->nullable();

            $table->timestamps();

            $table->unique(
                ['sport_competition_id', 'scope_type', 'scope_id'],
                'sport_participants_unique_scope'
            );
            $table->index(['tenant_id', 'scope_type']);
        });

        Schema::create('sport_matches', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->nullable()
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('sport_competition_id')
                ->constrained('sport_competitions')
                ->cascadeOnDelete();

            // section | class | school | directorate | governorate | ministry
            $table->string('scope_type', 20);
            $table->unsignedTinyInteger('round_number')->default(1);
            $table->unsignedTinyInteger('bracket_slot')->nullable();

            $table->foreignId('home_participant_id')
                ->nullable()
                ->constrained('sport_participants')
                ->nullOnDelete();
            $table->foreignId('away_participant_id')
                ->nullable()
                ->constrained('sport_participants')
                ->nullOnDelete();

            $table->unsignedSmallInteger('home_score')->nullable();
            $table->unsignedSmallInteger('away_score')->nullable();

            $table->foreignId('winner_participant_id')
                ->nullable()
                ->constrained('sport_participants')
                ->nullOnDelete();

            // The match this winner advances to.
            $table->foreignId('next_match_id')
                ->nullable()
                ->constrained('sport_matches')
                ->nullOnDelete();

            // scheduled | live | played | walkover | cancelled
            $table->string('status', 20)->default('scheduled');
            $table->timestamp('played_at')->nullable();
            $table->string('venue', 120)->nullable();

            $table->timestamps();

            $table->index(['sport_competition_id', 'scope_type', 'round_number'], 'sport_matches_bracket_index');
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sport_matches');
        Schema::dropIfExists('sport_participants');
        Schema::dropIfExists('sport_competitions');
    }
};

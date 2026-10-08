<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Interactive learning activities for primary students (الألعاب التفاعلية).
 *
 * Students answer English / Maths / general-activity questions from the app;
 * each attempt is scored and can feed the competition metrics.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interactive_activities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->nullable()
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->string('title');
            // english | math | activities
            $table->string('subject_area', 20);
            // quiz | matching | spelling | counting | game
            $table->string('kind', 20)->default('quiz');
            $table->unsignedTinyInteger('grade')->nullable();
            $table->unsignedSmallInteger('time_limit_seconds')->nullable();
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'subject_area', 'grade']);
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('activity_questions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->nullable()
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('interactive_activity_id')
                ->constrained('interactive_activities')
                ->cascadeOnDelete();

            // The prompt students see; kept in English for language practice.
            $table->text('prompt_en');
            $table->text('prompt_ar')->nullable();
            $table->string('answer_type', 20)->default('choice');
            $table->json('options')->nullable();
            $table->text('correct_answer');
            $table->decimal('points', 5, 2)->default(1);
            $table->unsignedSmallInteger('sequence')->default(1);

            $table->timestamps();

            $table->index(['tenant_id', 'interactive_activity_id', 'sequence'], 'activity_questions_order_index');
        });

        Schema::create('activity_attempts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('interactive_activity_id')
                ->constrained('interactive_activities')
                ->cascadeOnDelete();

            $table->foreignId('student_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->decimal('score', 8, 2)->default(0);
            $table->decimal('max_score', 8, 2)->default(0);
            $table->unsignedSmallInteger('duration_seconds')->nullable();
            $table->json('answers')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'student_id']);
            $table->index(['tenant_id', 'interactive_activity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_attempts');
        Schema::dropIfExists('activity_questions');
        Schema::dropIfExists('interactive_activities');
    }
};

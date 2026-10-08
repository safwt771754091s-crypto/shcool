<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teaching content: curriculum units and the teacher's preparation notebook
 * (كراسة التحضير). This is the core of "everything related to teaching".
 *
 *   subject -> unit -> lesson -> preparation entry (per teacher, per section)
 *
 * A preparation entry records what the teacher planned and what actually
 * happened, which is what supervisors inspect.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curriculum_units', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('subject_id')
                ->constrained('subjects')
                ->cascadeOnDelete();

            $table->foreignId('term_id')
                ->nullable()
                ->constrained('terms')
                ->nullOnDelete();

            $table->string('name');
            $table->unsignedTinyInteger('sequence')->default(1);
            $table->text('description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'subject_id', 'sequence']);
        });

        Schema::create('lessons', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('curriculum_unit_id')
                ->constrained('curriculum_units')
                ->cascadeOnDelete();

            $table->string('title');
            $table->unsignedTinyInteger('sequence')->default(1);
            $table->unsignedSmallInteger('planned_periods')->default(1);
            $table->text('objectives')->nullable();
            $table->text('content')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'curriculum_unit_id', 'sequence']);
        });

        Schema::create('lesson_preparations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('lesson_id')
                ->constrained('lessons')
                ->cascadeOnDelete();

            $table->foreignId('teacher_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('class_section_id')
                ->nullable()
                ->constrained('class_sections')
                ->nullOnDelete();

            $table->date('scheduled_on');

            // draft | submitted | approved | returned
            $table->string('status', 20)->default('draft');

            $table->text('objectives')->nullable();
            $table->text('strategies')->nullable();     // teaching methods
            $table->text('resources')->nullable();      // aids / materials
            $table->text('homework')->nullable();
            $table->text('assessment')->nullable();     // how learning is checked
            $table->text('notes')->nullable();

            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_comment')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'teacher_id', 'scheduled_on']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('lesson_id')
                ->nullable()
                ->constrained('lessons')
                ->nullOnDelete();

            $table->foreignId('class_section_id')
                ->nullable()
                ->constrained('class_sections')
                ->nullOnDelete();

            $table->foreignId('teacher_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_on')->nullable();
            $table->decimal('max_mark', 5, 2)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'class_section_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('lesson_preparations');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('curriculum_units');
    }
};

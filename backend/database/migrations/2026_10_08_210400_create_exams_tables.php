<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Exams (الاختبارات) and the gradebook (رصد الدرجات).
 *
 * An exam belongs to a (subject, section, term) and carries its own max mark.
 * A `grade` is one student's mark in one exam; the term result sheet is derived
 * from these rows, never typed in by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('subject_id')
                ->constrained('subjects')
                ->cascadeOnDelete();

            $table->foreignId('class_section_id')
                ->constrained('class_sections')
                ->cascadeOnDelete();

            $table->foreignId('term_id')
                ->nullable()
                ->constrained('terms')
                ->nullOnDelete();

            $table->string('title');
            $table->string('type', 20)->default('monthly'); // daily|monthly|midterm|final|quiz
            $table->date('held_on')->nullable();
            $table->decimal('max_mark', 5, 2)->default(100);
            $table->decimal('pass_mark', 5, 2)->default(50);
            $table->decimal('weight', 5, 2)->default(1);
            $table->boolean('is_published')->default(false);
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'class_section_id']);
            $table->index(['tenant_id', 'subject_id', 'term_id']);
        });

        Schema::create('grades', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('exam_id')
                ->constrained('exams')
                ->cascadeOnDelete();

            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();

            $table->decimal('mark', 5, 2)->nullable();
            $table->boolean('is_absent')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('entered_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('entered_at')->nullable();

            $table->timestamps();

            $table->unique(['exam_id', 'student_id']);
            $table->index(['tenant_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grades');
        Schema::dropIfExists('exams');
    }
};

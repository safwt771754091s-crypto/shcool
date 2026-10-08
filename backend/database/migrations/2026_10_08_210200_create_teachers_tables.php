<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teachers (المعلمون) and their teaching assignments (التوزيع).
 *
 * A teacher is a staff profile attached to a `users` account. A teaching
 * assignment links a teacher to a (subject, section) pair for one academic
 * year — that is the row the attendance, gradebook and timetable all key on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('employee_number', 40);
            $table->string('full_name');
            $table->string('gender', 10)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('national_id', 50)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('specialization')->nullable();   // التخصص
            $table->string('job_title')->nullable();        // معلم | معلم أول | وكيل
            $table->string('qualification')->nullable();    // المؤهل العلمي
            $table->date('hired_on')->nullable();
            $table->string('status', 20)->default('active'); // active|leave|retired|terminated

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'employee_number']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('teaching_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('teacher_id')
                ->constrained('teachers')
                ->cascadeOnDelete();

            $table->foreignId('subject_id')
                ->constrained('subjects')
                ->cascadeOnDelete();

            $table->foreignId('class_section_id')
                ->constrained('class_sections')
                ->cascadeOnDelete();

            $table->foreignId('academic_year_id')
                ->nullable()
                ->constrained('academic_years')
                ->nullOnDelete();

            $table->unsignedTinyInteger('weekly_periods')->default(1);
            $table->boolean('is_homeroom')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['teacher_id', 'subject_id', 'class_section_id', 'academic_year_id'],
                'teaching_assignment_unique'
            );
            $table->index(['tenant_id', 'teacher_id']);
            $table->index(['tenant_id', 'class_section_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_assignments');
        Schema::dropIfExists('teachers');
    }
};

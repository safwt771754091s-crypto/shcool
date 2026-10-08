<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Students (الطلاب) and the admission workflow (القبول).
 *
 * A student profile exists independently of a login account: young pupils are
 * enrolled by the school, and a `users` row is attached later only when the
 * student (or a guardian) actually needs to sign in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            // Optional login account for the student.
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('class_section_id')
                ->nullable()
                ->constrained('class_sections')
                ->nullOnDelete();

            $table->string('student_number', 40);      // الرقم الإحصائي داخل المدرسة
            $table->string('full_name');
            $table->string('gender', 10)->nullable();  // male | female
            $table->date('birth_date')->nullable();
            $table->string('national_id', 50)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->string('photo')->nullable();

            // applicant -> enrolled -> graduated | withdrawn | transferred
            $table->string('status', 20)->default('applicant');
            $table->date('admitted_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'student_number']);
            $table->index(['tenant_id', 'class_section_id']);
            $table->index(['tenant_id', 'status']);
        });

        // Admission applications: the intake funnel before enrolment.
        Schema::create('admissions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('academic_year_id')
                ->nullable()
                ->constrained('academic_years')
                ->nullOnDelete();

            // The grade/section the applicant is applying for.
            $table->foreignId('school_class_id')
                ->nullable()
                ->constrained('school_classes')
                ->nullOnDelete();

            // Filled in once the applicant is accepted and enrolled.
            $table->foreignId('student_id')
                ->nullable()
                ->constrained('students')
                ->nullOnDelete();

            $table->string('applicant_name');
            $table->string('gender', 10)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('national_id', 50)->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('guardian_phone', 30)->nullable();
            $table->string('previous_school')->nullable();

            $table->string('status', 20)->default('submitted'); // submitted|accepted|rejected|enrolled
            $table->unsignedTinyInteger('score')->nullable();   // admission test / interview
            $table->text('decision_note')->nullable();
            $table->foreignId('decided_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('decided_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'academic_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admissions');
        Schema::dropIfExists('students');
    }
};

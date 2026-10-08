<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily attendance (الحضور اليومي) and absence reports (تقارير الغياب).
 *
 * One row per student per day per section. The `attendance_sessions` header
 * records who took the register, so a class's roll call is auditable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('class_section_id')
                ->constrained('class_sections')
                ->cascadeOnDelete();

            $table->date('attendance_date');
            $table->string('period', 20)->default('daily'); // daily | period label
            $table->foreignId('taken_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['class_section_id', 'attendance_date', 'period'], 'attendance_session_unique');
            $table->index(['tenant_id', 'attendance_date']);
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('attendance_session_id')
                ->constrained('attendance_sessions')
                ->cascadeOnDelete();

            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();

            // present | absent | late | excused
            $table->string('status', 20)->default('present');
            $table->unsignedSmallInteger('late_minutes')->nullable();
            $table->string('absence_reason')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['attendance_session_id', 'student_id'], 'attendance_unique_student');
            $table->index(['tenant_id', 'student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('attendance_sessions');
    }
};

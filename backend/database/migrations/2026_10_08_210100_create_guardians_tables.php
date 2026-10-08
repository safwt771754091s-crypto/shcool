<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guardians / parents (أولياء الأمور) and their link to students.
 *
 * A guardian is a `users` account (so they can log into the parent portal) and
 * may be linked to several students. `guardian_student` carries the relation
 * type so siblings, mothers and fathers are all represented correctly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardians', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('full_name');
            $table->string('relation', 20)->default('father'); // father|mother|guardian
            $table->string('phone', 30)->nullable();
            $table->string('alt_phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('national_id', 50)->nullable();
            $table->string('job_title')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'phone']);
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('guardian_student', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('guardian_id')
                ->constrained('guardians')
                ->cascadeOnDelete();

            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();

            $table->string('relation', 20)->default('father');
            $table->boolean('is_primary')->default(false);
            $table->boolean('can_pickup')->default(true);

            $table->timestamps();

            $table->unique(['guardian_id', 'student_id']);
            $table->index(['tenant_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_student');
        Schema::dropIfExists('guardians');
    }
};

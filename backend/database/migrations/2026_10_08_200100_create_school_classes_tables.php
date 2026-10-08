<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Classes (الصفوف) and their sections (الشعب).
 *
 * A class is a grade level inside one school ("الصف الأول الابتدائي").
 * A section is a cohort of that grade ("الأول - شعبة أ").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_classes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('branch_id')
                ->nullable()
                ->constrained('organizations')
                ->nullOnDelete();

            $table->string('name');                // "الصف الأول الابتدائي"
            $table->unsignedTinyInteger('grade');  // 1..12
            $table->string('stage', 20)->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'grade']);
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('class_sections', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('school_class_id')
                ->constrained('school_classes')
                ->cascadeOnDelete();

            $table->string('name', 50);            // "أ"
            $table->unsignedSmallInteger('capacity')->default(30);
            $table->string('room', 50)->nullable();

            $table->foreignId('homeroom_teacher_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_class_id', 'name']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_sections');
        Schema::dropIfExists('school_classes');
    }
};

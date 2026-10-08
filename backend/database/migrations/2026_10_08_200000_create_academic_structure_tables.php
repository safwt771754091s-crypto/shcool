<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Academic structure: years, terms and subjects. All three are tenant-scoped
 * so every school owns its own calendar and subject catalogue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->string('name', 50);            // "2025-2026"
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_current')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'name']);
            $table->index(['tenant_id', 'is_current']);
        });

        Schema::create('terms', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('academic_year_id')
                ->constrained('academic_years')
                ->cascadeOnDelete();

            $table->string('name', 50);            // "الفصل الأول"
            $table->unsignedTinyInteger('sequence')->default(1);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_current')->default(false);

            $table->timestamps();

            $table->unique(['academic_year_id', 'sequence']);
            $table->index(['tenant_id', 'is_current']);
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->string('name');                // "الرياضيات"
            $table->string('code', 30)->nullable();
            $table->string('stage', 20)->nullable(); // primary | intermediate | secondary
            $table->decimal('pass_mark', 5, 2)->default(50);
            $table->decimal('max_mark', 5, 2)->default(100);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('terms');
        Schema::dropIfExists('academic_years');
    }
};

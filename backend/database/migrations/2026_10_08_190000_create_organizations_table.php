<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The single table that models the whole administrative hierarchy:
     * ministry -> governorate -> directorate -> school -> branch.
     *
     * A "tenant" is always a school-level organization. Branches belong to
     * their school and therefore share its tenant_id.
     */
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('organizations')
                ->nullOnDelete();

            // ministry | governorate | directorate | school | branch
            $table->string('type', 20);
            // 1..5, mirrors the level order in the hierarchy
            $table->unsignedTinyInteger('level');

            $table->string('name');
            $table->string('code', 50)->unique();
            $table->string('slug')->unique();

            // Materialized path of the node, e.g. "/1/4/9/".
            // Enables fast descendant lookups (WHERE path LIKE '/1/4/%').
            $table->string('path', 255)->index();
            $table->unsignedTinyInteger('depth')->default(0);

            // The school this row is tenant-scoped to. Null for levels above
            // the school (ministry/governorate/directorate) and for the school
            // row itself until it is created.
            $table->foreignId('tenant_id')
                ->nullable()
                ->constrained('organizations')
                ->nullOnDelete();

            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'parent_id']);
            $table->index(['tenant_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Membership pivot: which organizations a user belongs to.
     *
     * A user may belong to several organizations (e.g. a teacher working in
     * two schools). The one flagged is_primary is the user's "home"
     * organization and drives default tenant resolution.
     */
    public function up(): void
    {
        Schema::create('organization_user', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('job_title')->nullable();
            $table->boolean('is_primary')->default(false);

            $table->timestamps();

            $table->unique(['organization_id', 'user_id']);
            $table->index(['user_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_user');
    }
};

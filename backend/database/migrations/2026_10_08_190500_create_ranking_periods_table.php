<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A time window over which competition scores are aggregated, e.g. a term,
 * a month or a full academic year. Rankings are always produced per period.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ranking_periods', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->nullable()
                ->constrained('organizations')
                ->nullOnDelete();

            $table->string('name');
            // term | semester | month | year | custom
            $table->string('type', 20)->default('term');

            $table->date('starts_on');
            $table->date('ends_on');

            $table->boolean('is_active')->default(true);
            $table->boolean('is_locked')->default(false);

            $table->timestamps();

            $table->index(['tenant_id', 'type']);
            $table->index(['starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ranking_periods');
    }
};

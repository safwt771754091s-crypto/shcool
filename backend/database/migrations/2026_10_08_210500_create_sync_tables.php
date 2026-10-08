<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offline sync (العمل دون إنترنت).
 *
 * Mobile clients queue mutations while offline and replay them later. Each
 * batch is recorded so a sync is idempotent: replaying the same client batch id
 * never applies the same change twice, and the per-item result is returned to
 * the device.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_batches', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->uuid('client_batch_id');
            $table->string('device_id', 80)->nullable();
            $table->unsignedInteger('item_count')->default(0);
            $table->unsignedInteger('applied_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->string('status', 20)->default('applied'); // applied|partial|failed
            $table->json('results')->nullable();
            $table->timestamp('received_at')->nullable();

            $table->timestamps();

            // Idempotency: the same device batch is only ever processed once.
            $table->unique(['tenant_id', 'client_batch_id'], 'sync_batch_unique');
            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_batches');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outbound message log (سجل الإشعارات).
 *
 * Every SMS/WhatsApp/in-app message the platform attempts is recorded here so
 * delivery is auditable and failed sends can be retried from the queue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->nullable()
                ->constrained('organizations')
                ->nullOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('channel', 20);         // sms | whatsapp | in_app
            $table->string('recipient', 40);       // phone number / user id / email
            $table->string('template_key', 120)->nullable();
            $table->string('subject', 255)->nullable();
            $table->text('body')->nullable();

            // queued | sending | sent | failed
            $table->string('status', 20)->default('queued');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('provider', 40)->nullable();     // twilio | meta | log ...
            $table->string('provider_message_id', 120)->nullable();
            $table->text('error')->nullable();
            $table->json('meta')->nullable();

            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'channel', 'created_at'], 'notif_log_channel_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};

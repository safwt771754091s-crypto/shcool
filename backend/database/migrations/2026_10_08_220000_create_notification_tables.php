<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notification templates (قوالب الإشعارات) and per-user channel preferences.
 *
 * Templates are tenant-scoped but may also exist at the platform level with a
 * null tenant_id (national wording). A school resolves its own template first
 * and falls back to the platform default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->nullable()
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->string('key', 120);            // e.g. attendance.absence_alert
            $table->string('channel', 20);         // sms | whatsapp | in_app
            $table->string('locale', 8)->default('ar');
            $table->string('title', 255)->nullable();
            $table->text('body');                  // supports :student_name placeholders
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // One template per key/channel/locale inside a tenant.
            $table->unique(['tenant_id', 'key', 'channel', 'locale'], 'notif_tpl_unique');
            $table->index(['key', 'channel']);
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('channel', 20);         // sms | whatsapp | in_app
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'channel'], 'notif_pref_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notification_templates');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI agents module (وكلاء الذكاء الاصطناعي).
 *
 * Stores the chat history between a user and an agent so conversations are
 * durable and auditable. Everything is tenant-scoped: the AI only ever sees the
 * data of the school the signed-in user belongs to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->nullable()
                ->constrained('organizations')
                ->nullOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // The agent persona key (e.g. "manager_assistant").
            $table->string('agent', 60);

            $table->string('title')->nullable();
            $table->timestamp('last_message_at')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'user_id']);
            $table->index(['user_id', 'last_message_at']);
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->nullable()
                ->constrained('organizations')
                ->nullOnDelete();

            $table->foreignId('conversation_id')
                ->constrained('ai_conversations')
                ->cascadeOnDelete();

            // user | assistant | system | tool
            $table->string('role', 20);

            $table->text('content');

            // Tool calls the assistant requested, and the results returned.
            $table->json('tool_calls')->nullable();
            $table->json('tool_results')->nullable();

            $table->string('model', 120)->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'conversation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};

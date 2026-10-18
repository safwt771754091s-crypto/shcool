<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer service assistant (مساعد خدمة العملاء).
 *
 * Five tables, all tenant-scoped:
 *   kb_articles               — the approved knowledge base the assistant answers from.
 *   support_conversations     — an assistant chat session, owned by one user.
 *   support_messages          — the turns of that chat (with intent/confidence/sources).
 *   support_tickets           — an escalation handed to a human agent.
 *   support_ticket_messages   — the human thread on a ticket.
 *
 * kb_articles may also hold platform-wide rows (tenant_id NULL) authored by the
 * owner; those are visible to every school (includesGlobalRows).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_articles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->nullable()
                ->constrained('organizations')
                ->nullOnDelete();

            $table->string('category', 60);
            $table->string('locale', 8)->default('ar');
            $table->string('question');
            $table->text('answer');
            // Extra search terms (synonyms, misspellings) to improve retrieval.
            $table->json('keywords')->nullable();
            $table->boolean('published')->default(true);
            // Higher priority passages are preferred when scores tie.
            $table->unsignedSmallInteger('priority')->default(0);

            $table->timestamps();

            $table->index(['tenant_id', 'category', 'locale']);
            $table->index(['published']);
        });

        Schema::create('support_conversations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->nullable()
                ->constrained('organizations')
                ->nullOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('title')->nullable();
            // Last classified intent, for the desk's queue and analytics.
            $table->string('intent', 60)->nullable();
            $table->string('locale', 8)->default('ar');
            $table->timestamp('last_message_at')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'user_id']);
            $table->index(['user_id', 'last_message_at']);
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->nullable()
                ->constrained('organizations')
                ->nullOnDelete();

            $table->foreignId('conversation_id')
                ->constrained('support_conversations')
                ->cascadeOnDelete();

            // user | assistant | system
            $table->string('role', 20);
            $table->text('content');

            $table->string('intent', 60)->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            // Knowledge passages cited, and the classifier/provider used.
            $table->json('sources')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'conversation_id']);
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->nullable()
                ->constrained('organizations')
                ->nullOnDelete();

            // The user who raised the ticket.
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // The human agent working it (null until assigned).
            $table->foreignId('assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('conversation_id')
                ->nullable()
                ->constrained('support_conversations')
                ->nullOnDelete();

            $table->string('reference', 24)->unique();
            $table->string('subject');
            $table->string('category', 60)->default('general');

            // open | pending | resolved | closed
            $table->string('status', 20)->default('open');
            // low | normal | high | urgent
            $table->string('priority', 20)->default('normal');

            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'status', 'priority']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('support_ticket_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->nullable()
                ->constrained('organizations')
                ->nullOnDelete();

            $table->foreignId('ticket_id')
                ->constrained('support_tickets')
                ->cascadeOnDelete();

            // Null for assistant/system notes.
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // user | agent | system
            $table->string('author_type', 20)->default('user');
            $table->text('body');
            // Internal notes are never shown to the requester.
            $table->boolean('internal')->default(false);

            $table->timestamps();

            $table->index(['tenant_id', 'ticket_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_conversations');
        Schema::dropIfExists('kb_articles');
    }
};

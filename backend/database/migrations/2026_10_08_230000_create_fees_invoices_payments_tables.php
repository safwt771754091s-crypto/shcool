<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Financial module: the fee catalogue (الرسوم), the invoices (الفواتير) issued
 * to students, their line items, and the payments (المدفوعات) collected against
 * them.
 *
 * money is stored as decimal(12,2) with a currency code, never as a float. An
 * invoice's paid_amount/balance are always derived from its payments so the two
 * can never drift out of sync.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_structures', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('academic_year_id')
                ->nullable()
                ->constrained('academic_years')
                ->nullOnDelete();

            // When set, the fee applies to one class level; when null, to the
            // whole school (e.g. registration or transport).
            $table->foreignId('school_class_id')
                ->nullable()
                ->constrained('school_classes')
                ->nullOnDelete();

            $table->string('name');
            $table->string('type', 20)->default('tuition'); // tuition|registration|transport|activity|other
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('YER');
            $table->date('due_on')->nullable();

            // The period this fee covers, used to avoid re-issuing invoices.
            $table->string('period', 20)->nullable(); // term|semester|year|month

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'academic_year_id']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();

            // Human-facing document number, unique inside a school.
            $table->string('number', 40);

            $table->string('title');

            $table->decimal('amount', 12, 2)->default(0);        // sum of items
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0);    // amount - discount
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);       // net_amount - paid_amount

            $table->string('currency', 3)->default('YER');

            $table->string('status', 20)->default('unpaid'); // unpaid|partial|paid|cancelled

            $table->date('issued_on');
            $table->date('due_on')->nullable();

            $table->text('notes')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'student_id']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('invoice_id')
                ->constrained('invoices')
                ->cascadeOnDelete();

            $table->foreignId('fee_structure_id')
                ->nullable()
                ->constrained('fee_structures')
                ->nullOnDelete();

            $table->string('description');
            $table->decimal('amount', 12, 2);

            $table->timestamps();

            $table->index(['tenant_id', 'invoice_id']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('invoice_id')
                ->constrained('invoices')
                ->cascadeOnDelete();

            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();

            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('YER');
            $table->string('method', 20)->default('cash'); // cash|bank_transfer|card|online|other
            $table->string('reference')->nullable();       // gateway / transfer ref
            $table->string('receipt_number', 40)->nullable();

            $table->timestamp('paid_at');

            $table->foreignId('received_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'invoice_id']);
            $table->index(['tenant_id', 'student_id']);
            $table->index(['tenant_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('fee_structures');
    }
};

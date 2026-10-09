<?php

namespace App\Services\Finance;

use App\Models\Finance\FeeStructure;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use App\Models\Student\Student;
use App\Models\User;
use App\Support\Tenancy\TenantManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Fees, invoices and payments (الرسوم والفواتير والمدفوعات).
 *
 * The golden rule: an invoice's paid_amount/balance/status are derived from its
 * payment rows every time money moves, so a statement can never disagree with
 * the collections.
 */
class FinanceService
{
    /**
     * Issue an invoice for a student with one or more line items.
     *
     * @param  array<int, array{fee_structure_id?: int|null, description: string, amount: float|int|string}>  $items
     */
    public function issueInvoice(
        Student $student,
        array $items,
        ?float $discount = 0.0,
        ?string $currency = null,
        ?string $title = null,
        ?string $dueOn = null,
        ?User $actor = null,
    ): Invoice {
        return DB::transaction(function () use ($student, $items, $discount, $currency, $title, $dueOn, $actor) {
            $amount = collect($items)->sum(fn ($item) => (float) $item['amount']);
            $discount = (float) ($discount ?? 0);
            $net = round($amount - $discount, 2);
            $currency ??= $items[0]['currency'] ?? 'YER';

            $invoice = Invoice::create([
                'student_id' => $student->getKey(),
                'number' => $this->nextNumber(),
                'title' => $title ?? 'فاتورة رسوم دراسية',
                'amount' => $amount,
                'discount' => $discount,
                'net_amount' => $net,
                'paid_amount' => 0,
                'balance' => $net,
                'currency' => $currency,
                'status' => Invoice::STATUS_UNPAID,
                'issued_on' => now()->toDateString(),
                'due_on' => $dueOn,
                'created_by' => $actor?->getKey(),
            ]);

            foreach ($items as $item) {
                $invoice->items()->create([
                    'fee_structure_id' => $item['fee_structure_id'] ?? null,
                    'description' => $item['description'],
                    'amount' => (float) $item['amount'],
                ]);
            }

            return $invoice->load('items');
        });
    }

    /**
     * Issue invoices for a whole term's fees to every enrolled student in a
     * class level (or the whole school when $schoolClassId is null).
     *
     * @param  Collection<int, FeeStructure>  $fees
     * @return int number of invoices issued
     */
    public function issueTermInvoices(Collection $fees, ?int $schoolClassId = null): int
    {
        $students = Student::query()
            ->where('status', Student::STATUS_ENROLLED)
            ->when($schoolClassId !== null, fn ($q) => $q->whereHas(
                'section',
                fn ($s) => $s->where('school_class_id', $schoolClassId),
            ))
            ->get();

        $items = $fees->map(fn (FeeStructure $fee) => [
            'fee_structure_id' => $fee->getKey(),
            'description' => $fee->name,
            'amount' => (float) $fee->amount,
        ])->all();

        $count = 0;

        foreach ($students as $student) {
            $this->issueInvoice($student, $items);
            $count++;
        }

        return $count;
    }

    /**
     * Record a payment and recompute the invoice's paid/balance/status.
     */
    public function recordPayment(
        Invoice $invoice,
        float $amount,
        string $method = Payment::METHOD_CASH,
        ?string $reference = null,
        ?User $receivedBy = null,
        ?string $paidAt = null,
        ?string $notes = null,
    ): Payment {
        return DB::transaction(function () use ($invoice, $amount, $method, $reference, $receivedBy, $paidAt, $notes) {
            if ($invoice->isCancelled()) {
                throw new \RuntimeException('لا يمكن تسجيل دفعة على فاتورة ملغاة.');
            }

            $payment = $invoice->payments()->create([
                'student_id' => $invoice->student_id,
                'amount' => $amount,
                'currency' => $invoice->currency,
                'method' => $method,
                'reference' => $reference,
                'receipt_number' => $this->nextReceiptNumber(),
                'paid_at' => $paidAt ? now()->parse($paidAt) : now(),
                'received_by' => $receivedBy?->getKey(),
                'notes' => $notes,
            ]);

            $this->recalculate($invoice);

            return $payment;
        });
    }

    /**
     * Recompute paid_amount, balance and status from the payment rows.
     */
    public function recalculate(Invoice $invoice): Invoice
    {
        $paid = (float) $invoice->payments()->sum('amount');
        $net = (float) $invoice->net_amount;
        $balance = round($net - $paid, 2);

        $status = Invoice::STATUS_UNPAID;

        if ($invoice->status !== Invoice::STATUS_CANCELLED) {
            if ($paid <= 0) {
                $status = Invoice::STATUS_UNPAID;
            } elseif ($balance <= 0.009) {
                $status = Invoice::STATUS_PAID;
            } else {
                $status = Invoice::STATUS_PARTIAL;
            }
        }

        $invoice->forceFill([
            'paid_amount' => $paid,
            'balance' => max($balance, 0),
            'status' => $invoice->status === Invoice::STATUS_CANCELLED ? Invoice::STATUS_CANCELLED : $status,
        ])->save();

        return $invoice;
    }

    /**
     * Cancel an invoice (kept for the audit trail, excluded from balances).
     */
    public function cancel(Invoice $invoice, ?string $reason = null): Invoice
    {
        $invoice->forceFill([
            'status' => Invoice::STATUS_CANCELLED,
            'notes' => trim(($invoice->notes ? $invoice->notes.' ' : '').($reason ?? '')),
        ])->save();

        return $invoice;
    }

    /**
     * Outstanding balance for a student, per currency.
     *
     * @return array{total_due: float, by_currency: array<string, float>}
     */
    public function studentBalance(Student $student): array
    {
        $invoices = Invoice::query()
            ->where('student_id', $student->getKey())
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->get(['balance', 'currency']);

        $byCurrency = $invoices->groupBy('currency')
            ->map(fn (Collection $group) => round((float) $group->sum('balance'), 2))
            ->all();

        return [
            'total_due' => round((float) $invoices->sum('balance'), 2),
            'by_currency' => $byCurrency,
        ];
    }

    /**
     * School-wide collection summary for a date range.
     *
     * @return array{invoiced: float, collected: float, outstanding: float, by_method: array<string, float>, invoices: array<string, int>}
     */
    public function collectionSummary(?string $from = null, ?string $to = null): array
    {
        $invoices = Invoice::query()
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->get();

        $payments = Payment::query()
            ->when($from, fn ($q) => $q->whereDate('paid_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('paid_at', '<=', $to))
            ->get();

        return [
            'invoiced' => round((float) $invoices->sum('net_amount'), 2),
            'collected' => round((float) $payments->sum('amount'), 2),
            'outstanding' => round((float) $invoices->sum('balance'), 2),
            'by_method' => $payments->groupBy('method')
                ->map(fn (Collection $g) => round((float) $g->sum('amount'), 2))
                ->all(),
            'invoices' => Invoice::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->map(fn ($n) => (int) $n)
                ->all(),
        ];
    }

    /**
     * A sequential invoice number inside the current school.
     */
    protected function nextNumber(): string
    {
        $year = now()->format('Y');
        // Soft-deleted rows still occupy their number, so count them too.
        $count = (int) Invoice::allTenants()->withTrashed()
            ->where('tenant_id', $this->currentTenantId())
            ->count() + 1;

        return sprintf('INV-%s-%05d', $year, $count);
    }

    protected function nextReceiptNumber(): string
    {
        $year = now()->format('Y');
        $count = (int) Payment::allTenants()->where('tenant_id', $this->currentTenantId())->count() + 1;

        return sprintf('RC-%s-%06d', $year, $count);
    }

    protected function currentTenantId(): ?int
    {
        return app(TenantManager::class)->tenantId();
    }
}

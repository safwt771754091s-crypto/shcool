<?php

namespace Tests\Feature;

use App\Models\Academic\SchoolClass;
use App\Models\Finance\FeeStructure;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use App\Models\Organization;
use App\Models\Student\Student;
use App\Services\Finance\FinanceService;
use App\Services\Reports\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{school: Organization, students: Collection}
     */
    protected function school(int $count = 2): array
    {
        $school = Organization::factory()->tenant()->create();
        $this->actingAsTenant($school);

        $class = SchoolClass::create(['name' => 'الأول', 'grade' => 1]);
        $section = $class->sections()->create(['name' => 'أ']);

        $students = collect(range(1, $count))->map(fn (int $i) => Student::create([
            'class_section_id' => $section->getKey(),
            'student_number' => 'S-000'.$i,
            'full_name' => 'طالب '.$i,
            'status' => Student::STATUS_ENROLLED,
        ]));

        return compact('school', 'section', 'students');
    }

    public function test_invoice_totals_are_computed_from_items_and_discount(): void
    {
        ['students' => $students] = $this->school();

        $invoice = app(FinanceService::class)->issueInvoice(
            $students[0],
            [
                ['description' => 'رسوم دراسية', 'amount' => 1000],
                ['description' => 'رسوم نقل', 'amount' => 500],
            ],
            discount: 200,
        );

        $this->assertSame('1300.00', $invoice->net_amount);
        $this->assertSame('0.00', $invoice->paid_amount);
        $this->assertSame('1300.00', $invoice->balance);
        $this->assertSame(Invoice::STATUS_UNPAID, $invoice->status);
        $this->assertCount(2, $invoice->items);
        $this->assertMatchesRegularExpression('/^INV-\d{4}-\d{5}$/', $invoice->number);
    }

    public function test_payments_update_balance_and_status_progressively(): void
    {
        ['students' => $students] = $this->school();
        $finance = app(FinanceService::class);

        $invoice = $finance->issueInvoice($students[0], [
            ['description' => 'رسوم دراسية', 'amount' => 1000],
        ]);

        // Partial payment.
        $finance->recordPayment($invoice, 300);
        $this->assertSame(Invoice::STATUS_PARTIAL, $invoice->refresh()->status);
        $this->assertSame('300.00', $invoice->paid_amount);
        $this->assertSame('700.00', $invoice->balance);

        // Second partial payment.
        $finance->recordPayment($invoice, 200, Payment::METHOD_BANK_TRANSFER);
        $this->assertSame('500.00', $invoice->refresh()->paid_amount);
        $this->assertSame('500.00', $invoice->balance);

        // Final payment settles it.
        $payment = $finance->recordPayment($invoice, 500, Payment::METHOD_CARD);
        $this->assertSame(Invoice::STATUS_PAID, $invoice->refresh()->status);
        $this->assertSame('0.00', $invoice->balance);
        $this->assertMatchesRegularExpression('/^RC-\d{4}-\d{6}$/', $payment->receipt_number);
    }

    public function test_cancelled_invoice_rejects_payment_and_is_excluded_from_balance(): void
    {
        ['students' => $students] = $this->school();
        $finance = app(FinanceService::class);

        $invoice = $finance->issueInvoice($students[0], [
            ['description' => 'رسوم دراسية', 'amount' => 1000],
        ]);

        $finance->cancel($invoice, 'خطأ في المبلغ');
        $this->assertSame(Invoice::STATUS_CANCELLED, $invoice->refresh()->status);

        $this->expectException(\RuntimeException::class);
        $finance->recordPayment($invoice, 100);
    }

    public function test_term_issuance_generates_one_invoice_per_enrolled_student(): void
    {
        ['students' => $students] = $this->school(3);
        $finance = app(FinanceService::class);

        $fees = collect([
            FeeStructure::create(['name' => 'رسوم دراسية', 'amount' => 900, 'is_active' => true]),
            FeeStructure::create(['name' => 'رسوم نشاط', 'amount' => 100, 'type' => 'activity', 'is_active' => true]),
        ]);

        $issued = $finance->issueTermInvoices($fees);

        $this->assertSame(3, $issued);
        $this->assertSame(3, Invoice::query()->count());
        $this->assertSame('1000.00', Invoice::query()->first()->net_amount);
    }

    public function test_student_balance_sums_uncancelled_invoices(): void
    {
        ['students' => $students] = $this->school();
        $finance = app(FinanceService::class);

        $a = $finance->issueInvoice($students[0], [['description' => 'أ', 'amount' => 500]]);
        $finance->issueInvoice($students[0], [['description' => 'ب', 'amount' => 300]]);
        $finance->recordPayment($a, 200);

        $balance = $finance->studentBalance($students[0]);

        $this->assertSame(600.0, $balance['total_due']);
        $this->assertSame(600.0, $balance['by_currency'][$a->currency]);
    }

    public function test_collection_summary_reports_invoiced_collected_and_outstanding(): void
    {
        ['students' => $students] = $this->school();
        $finance = app(FinanceService::class);

        $invoice = $finance->issueInvoice($students[0], [['description' => 'رسوم', 'amount' => 1000]]);
        $finance->recordPayment($invoice, 400, Payment::METHOD_CASH);

        $summary = $finance->collectionSummary();

        $this->assertSame(1000.0, $summary['invoiced']);
        $this->assertSame(400.0, $summary['collected']);
        $this->assertSame(600.0, $summary['outstanding']);
        $this->assertSame(400.0, $summary['by_method'][Payment::METHOD_CASH]);
        $this->assertSame(1, $summary['invoices'][Invoice::STATUS_PARTIAL]);
    }

    public function test_invoices_report_is_available_for_export(): void
    {
        ['students' => $students] = $this->school();
        $finance = app(FinanceService::class);

        $finance->issueInvoice($students[0], [['description' => 'رسوم', 'amount' => 1000]]);
        $finance->issueInvoice($students[1], [['description' => 'رسوم', 'amount' => 1000]]);

        $report = app(ReportService::class)->build('invoices');

        $this->assertSame('تقرير الفواتير', $report['title']);
        $this->assertCount(2, $report['rows']);
        $this->assertContains('invoices', ReportService::types());
    }
}

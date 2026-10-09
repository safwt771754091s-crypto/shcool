<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Finance\FeeStructure;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use App\Models\Student\Student;
use App\Services\Finance\FinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Fees (الرسوم), invoices (الفواتير) and payments (المدفوعات).
 */
class FinanceController extends Controller
{
    public function __construct(protected FinanceService $finance) {}

    // ---- Fee catalogue ------------------------------------------------

    public function fees(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'school_class_id' => ['sometimes', 'integer', 'exists:school_classes,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $query = FeeStructure::query()
            ->with('schoolClass')
            ->orderBy('name');

        if (isset($validated['school_class_id'])) {
            $query->where('school_class_id', $validated['school_class_id']);
        }

        if (array_key_exists('is_active', $validated)) {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function storeFee(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['sometimes', Rule::in(FeeStructure::TYPES)],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'school_class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
            'due_on' => ['nullable', 'date'],
            'period' => ['nullable', Rule::in(['term', 'semester', 'year', 'month'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $fee = FeeStructure::create($validated);

        return response()->json([
            'data' => $fee,
            'message' => 'تمت إضافة الرسم.',
        ], 201);
    }

    // ---- Invoices -----------------------------------------------------

    public function invoices(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => ['sometimes', 'integer', 'exists:students,id'],
            'status' => ['sometimes', Rule::in(Invoice::STATUSES)],
            'search' => ['sometimes', 'string', 'max:100'],
        ]);

        $query = Invoice::query()
            ->with(['student:id,full_name,student_number'])
            ->orderByDesc('id');

        if (isset($validated['student_id'])) {
            $query->where('student_id', $validated['student_id']);
        }

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if ($search = $validated['search'] ?? null) {
            $query->where(function ($q) use ($search): void {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhereHas('student', fn ($s) => $s->where('full_name', 'like', "%{$search}%"));
            });
        }

        return response()->json(['data' => $query->paginate(50)]);
    }

    /**
     * Issue an invoice to a student with one or more line items.
     */
    public function storeInvoice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'due_on' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.fee_structure_id' => ['nullable', 'integer', 'exists:fee_structures,id'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.amount' => ['required', 'numeric', 'min:0'],
        ]);

        $student = Student::query()->findOrFail($validated['student_id']);

        $invoice = $this->finance->issueInvoice(
            $student,
            $validated['items'],
            (float) ($validated['discount'] ?? 0),
            null,
            $validated['title'] ?? null,
            $validated['due_on'] ?? null,
            $request->user(),
        );

        return response()->json([
            'data' => $invoice,
            'message' => 'تمت إصدار الفاتورة.',
        ], 201);
    }

    public function showInvoice(Invoice $invoice): JsonResponse
    {
        return response()->json([
            'data' => $invoice->load(['items', 'payments.receivedBy:id,name', 'student:id,full_name,student_number']),
        ]);
    }

    /**
     * Bulk-issue a term's fees to every enrolled student.
     */
    public function issueTerm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fee_ids' => ['required', 'array', 'min:1'],
            'fee_ids.*' => ['integer', 'exists:fee_structures,id'],
            'school_class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
        ]);

        $fees = FeeStructure::query()->whereIn('id', $validated['fee_ids'])->get();

        $issued = $this->finance->issueTermInvoices($fees, $validated['school_class_id'] ?? null);

        return response()->json([
            'data' => ['invoices_issued' => $issued],
            'message' => "تمت إصدار {$issued} فاتورة.",
        ]);
    }

    public function cancelInvoice(Request $request, Invoice $invoice): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $invoice = $this->finance->cancel($invoice, $validated['reason'] ?? null);

        return response()->json([
            'data' => $invoice,
            'message' => 'تم إلغاء الفاتورة.',
        ]);
    }

    // ---- Payments -----------------------------------------------------

    public function payments(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invoice_id' => ['sometimes', 'integer', 'exists:invoices,id'],
            'student_id' => ['sometimes', 'integer', 'exists:students,id'],
            'method' => ['sometimes', Rule::in(Payment::METHODS)],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
        ]);

        $query = Payment::query()
            ->with(['student:id,full_name,student_number', 'invoice:id,number'])
            ->orderByDesc('paid_at');

        foreach (['invoice_id', 'student_id', 'method'] as $field) {
            if (isset($validated[$field])) {
                $query->where($field, $validated[$field]);
            }
        }

        if (isset($validated['from'])) {
            $query->whereDate('paid_at', '>=', $validated['from']);
        }

        if (isset($validated['to'])) {
            $query->whereDate('paid_at', '<=', $validated['to']);
        }

        return response()->json(['data' => $query->paginate(50)]);
    }

    /**
     * Collect a payment against an invoice and refresh its balance.
     */
    public function storePayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['sometimes', Rule::in(Payment::METHODS)],
            'reference' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $invoice = Invoice::query()->findOrFail($validated['invoice_id']);

        $payment = $this->finance->recordPayment(
            $invoice,
            (float) $validated['amount'],
            $validated['method'] ?? Payment::METHOD_CASH,
            $validated['reference'] ?? null,
            $request->user(),
            $validated['paid_at'] ?? null,
            $validated['notes'] ?? null,
        );

        return response()->json([
            'data' => [
                'payment' => $payment,
                'invoice' => $invoice->refresh(),
            ],
            'message' => 'تم تسجيل الدفعة.',
        ], 201);
    }

    // ---- Reporting ----------------------------------------------------

    /**
     * Outstanding balance for a student.
     */
    public function studentBalance(Student $student): JsonResponse
    {
        return response()->json([
            'data' => array_merge(
                ['student' => ['id' => $student->getKey(), 'full_name' => $student->full_name]],
                $this->finance->studentBalance($student),
            ),
        ]);
    }

    /**
     * School-wide collection summary.
     */
    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
        ]);

        return response()->json([
            'data' => $this->finance->collectionSummary(
                $validated['from'] ?? null,
                $validated['to'] ?? null,
            ),
        ]);
    }
}

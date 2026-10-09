/// Finance models: fee structures, invoices, payments and summary.
library;

double _double(dynamic v) {
  if (v == null) return 0;
  if (v is num) return v.toDouble();
  return double.tryParse(v.toString()) ?? 0;
}

class FeeStructure {
  FeeStructure({
    required this.id,
    required this.name,
    this.type,
    this.amount = 0,
    this.currency = 'YER',
    this.className,
    this.dueOn,
    this.period,
    this.isActive = true,
  });

  final int id;
  final String name;
  final String? type;
  final double amount;
  final String currency;
  final String? className;
  final String? dueOn;
  final String? period;
  final bool isActive;

  String get typeLabel => switch (type) {
        'tuition' => 'رسوم دراسية',
        'registration' => 'رسوم تسجيل',
        'transport' => 'مواصلات',
        'activity' => 'أنشطة',
        _ => type ?? 'أخرى',
      };

  factory FeeStructure.fromJson(Map<String, dynamic> json) {
    final schoolClass =
        (json['school_class'] as Map?)?.cast<String, dynamic>();
    return FeeStructure(
      id: (json['id'] as num).toInt(),
      name: json['name']?.toString() ?? '',
      type: json['type'] as String?,
      amount: _double(json['amount']),
      currency: json['currency']?.toString() ?? 'YER',
      className: schoolClass?['name']?.toString(),
      dueOn: json['due_on'] as String?,
      period: json['period'] as String?,
      isActive: json['is_active'] != false,
    );
  }
}

class Invoice {
  Invoice({
    required this.id,
    required this.number,
    this.studentName,
    this.studentNumber,
    this.title,
    this.netAmount = 0,
    this.paidAmount = 0,
    this.balance = 0,
    this.currency = 'YER',
    this.status,
    this.dueOn,
  });

  final int id;
  final String number;
  final String? studentName;
  final String? studentNumber;
  final String? title;
  final double netAmount;
  final double paidAmount;
  final double balance;
  final String currency;
  final String? status;
  final String? dueOn;

  String get statusLabel => switch (status) {
        'unpaid' => 'غير مدفوعة',
        'partial' => 'مدفوعة جزئياً',
        'paid' => 'مدفوعة',
        'cancelled' => 'ملغاة',
        _ => status ?? '—',
      };

  factory Invoice.fromJson(Map<String, dynamic> json) {
    final student = (json['student'] as Map?)?.cast<String, dynamic>();
    return Invoice(
      id: (json['id'] as num).toInt(),
      number: json['number']?.toString() ?? '',
      studentName: student?['full_name']?.toString(),
      studentNumber: student?['student_number']?.toString(),
      title: json['title'] as String?,
      netAmount: _double(json['net_amount']),
      paidAmount: _double(json['paid_amount']),
      balance: _double(json['balance']),
      currency: json['currency']?.toString() ?? 'YER',
      status: json['status'] as String?,
      dueOn: json['due_on'] as String?,
    );
  }
}

class Payment {
  Payment({
    required this.id,
    this.invoiceNumber,
    this.studentName,
    this.amount = 0,
    this.currency = 'YER',
    this.method,
    this.receiptNumber,
    this.paidAt,
  });

  final int id;
  final String? invoiceNumber;
  final String? studentName;
  final double amount;
  final String currency;
  final String? method;
  final String? receiptNumber;
  final String? paidAt;

  String get methodLabel => switch (method) {
        'cash' => 'نقداً',
        'bank_transfer' => 'حوالة بنكية',
        'card' => 'بطاقة',
        'online' => 'إلكتروني',
        _ => method ?? 'أخرى',
      };

  factory Payment.fromJson(Map<String, dynamic> json) {
    final student = (json['student'] as Map?)?.cast<String, dynamic>();
    final invoice = (json['invoice'] as Map?)?.cast<String, dynamic>();
    return Payment(
      id: (json['id'] as num).toInt(),
      invoiceNumber: invoice?['number']?.toString(),
      studentName: student?['full_name']?.toString(),
      amount: _double(json['amount']),
      currency: json['currency']?.toString() ?? 'YER',
      method: json['method'] as String?,
      receiptNumber: json['receipt_number'] as String?,
      paidAt: json['paid_at'] as String?,
    );
  }
}

class FinanceSummary {
  FinanceSummary({
    this.invoiced = 0,
    this.collected = 0,
    this.outstanding = 0,
    this.byMethod = const {},
    this.invoices = const {},
  });

  final double invoiced;
  final double collected;
  final double outstanding;
  final Map<String, double> byMethod;
  final Map<String, int> invoices;

  factory FinanceSummary.fromJson(Map<String, dynamic> json) =>
      FinanceSummary(
        invoiced: _double(json['invoiced']),
        collected: _double(json['collected']),
        outstanding: _double(json['outstanding']),
        byMethod: (json['by_method'] as Map?)?.map(
              (k, v) => MapEntry(k.toString(), _double(v)),
            ) ??
            const {},
        invoices: (json['invoices'] as Map?)?.map(
              (k, v) => MapEntry(k.toString(), (v as num?)?.toInt() ?? 0),
            ) ??
            const {},
      );
}

/// A report of the generic shape `{title, headers[], rows[]}` returned by the
/// reporting endpoints (also used for school overviews).
class ReportTable {
  ReportTable({
    required this.title,
    this.headers = const [],
    this.rows = const [],
  });

  final String title;
  final List<String> headers;
  final List<Map<String, dynamic>> rows;

  factory ReportTable.fromJson(Map<String, dynamic> json) => ReportTable(
        title: json['title']?.toString() ?? 'تقرير',
        headers: (json['headers'] as List?)
                ?.map((e) => e.toString())
                .toList() ??
            const [],
        rows: (json['rows'] as List?)
                ?.map((e) => (e as Map).cast<String, dynamic>())
                .toList() ??
            const [],
      );
}

import '../core/network/api_client.dart';
import '../models/finance.dart';

/// Finance: fee structures, invoices, payments, summary and reports.
class FinanceService {
  FinanceService(this._api);

  final ApiClient _api;

  Future<List<FeeStructure>> fees({int? classId}) async {
    final data = await _api.get('fees',
        query: {'school_class_id': ?classId}) as Map<String, dynamic>;
    final payload = data['data'] as List? ?? const [];
    return payload
        .map((e) => FeeStructure.fromJson((e as Map).cast<String, dynamic>()))
        .toList();
  }

  Future<FeeStructure> createFee({
    required String name,
    required double amount,
    String? type,
    String? currency,
    int? classId,
    String? dueOn,
    String? period,
  }) async {
    final data = await _api.post('fees', data: {
      'name': name,
      'amount': amount,
      'type': ?type,
      'currency': ?currency,
      'school_class_id': ?classId,
      'due_on': ?dueOn,
      'period': ?period,
    }) as Map<String, dynamic>;
    return FeeStructure.fromJson((data['data'] as Map).cast<String, dynamic>());
  }

  Future<List<Invoice>> invoices({int? studentId, String? status}) async {
    final data = await _api.get('invoices', query: {
      'student_id': ?studentId,
      'status': ?status,
    }) as Map<String, dynamic>;
    final payload = data['data'];
    final rows = (payload is Map && payload['data'] is List)
        ? payload['data'] as List
        : payload as List? ?? const [];
    return rows
        .map((e) => Invoice.fromJson((e as Map).cast<String, dynamic>()))
        .toList();
  }

  Future<List<Payment>> payments({int? invoiceId, int? studentId}) async {
    final data = await _api.get('payments', query: {
      'invoice_id': ?invoiceId,
      'student_id': ?studentId,
    }) as Map<String, dynamic>;
    final payload = data['data'];
    final rows = (payload is Map && payload['data'] is List)
        ? payload['data'] as List
        : payload as List? ?? const [];
    return rows
        .map((e) => Payment.fromJson((e as Map).cast<String, dynamic>()))
        .toList();
  }

  Future<void> collectPayment({
    required int invoiceId,
    required double amount,
    String? method,
    String? reference,
  }) async {
    await _api.post('payments', data: {
      'invoice_id': invoiceId,
      'amount': amount,
      'method': ?method,
      'reference': ?reference,
    });
  }

  Future<FinanceSummary> summary({String? from, String? to}) async {
    final data = await _api.get('finance/summary', query: {
      'from': ?from,
      'to': ?to,
    }) as Map<String, dynamic>;
    return FinanceSummary.fromJson(
        (data['data'] as Map).cast<String, dynamic>());
  }

  /// One of `students|teachers|attendance|absence-alerts|results|invoices`.
  Future<ReportTable> report(String type, {Map<String, dynamic>? extra}) async {
    final data = await _api.get('reports',
        query: {'type': type, ...?extra}) as Map<String, dynamic>;
    return ReportTable.fromJson((data['data'] as Map).cast<String, dynamic>());
  }

  Future<ReportTable> schoolsOverview() async {
    final data = await _api.get('reports/schools-overview')
        as Map<String, dynamic>;
    return ReportTable.fromJson((data['data'] as Map).cast<String, dynamic>());
  }

  /// Download a report as raw bytes (format: pdf|xlsx|csv).
  Future<List<int>> export(
    String type, {
    required String format,
    Map<String, dynamic>? extra,
  }) =>
      _api.download('reports/export/$format',
          query: {'type': type, ...?extra});
}

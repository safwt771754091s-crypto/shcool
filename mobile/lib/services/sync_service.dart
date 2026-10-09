import 'dart:math';

import '../core/network/api_client.dart';
import '../core/storage/offline_store.dart';

/// Drives offline-first writes: queue locally, replay in batches when online.
class SyncService {
  SyncService(this._api, this._store);

  final ApiClient _api;
  final OfflineStore _store;

  Future<int> pendingCount() => _store.count();

  /// Record an offline attendance register for later sync. The server applies
  /// one student per item, so each record becomes its own queued entry keyed by
  /// student for idempotency.
  Future<void> queueAttendance({
    required int classSectionId,
    required String attendanceDate,
    required String period,
    required List<Map<String, dynamic>> records,
  }) async {
    for (final record in records) {
      final studentId = record['student_id'];
      await _store.enqueue({
        'type': 'attendance.register',
        'client_id': 'att-$classSectionId-$attendanceDate-$period-$studentId',
        'payload': {
          'class_section_id': classSectionId,
          'attendance_date': attendanceDate,
          'period': period,
          'student_id': studentId,
          'status': record['status'] ?? 'present',
          if (record['late_minutes'] != null) 'late_minutes': record['late_minutes'],
          if (record['absence_reason'] != null)
            'absence_reason': record['absence_reason'],
        },
      });
    }
  }

  /// Flush the queue to the server. Returns the number of synced items.
  Future<int> flush() async {
    final items = await _store.pending();
    if (items.isEmpty) return 0;

    final batchId = _uuid();
    final result = await _api.post('sync/push', data: {
      'client_batch_id': batchId,
      'device_id': 'flutter',
      'items': items,
    }) as Map<String, dynamic>;

    await _store.clear();

    final data = result['data'];
    if (data is Map<String, dynamic> && data['applied_count'] is num) {
      return (data['applied_count'] as num).toInt();
    }
    return items.length;
  }

  String _uuid() {
    final rnd = Random();
    String hex(int n) =>
        List.generate(n, (_) => rnd.nextInt(16).toRadixString(16)).join();
    return '${hex(8)}-${hex(4)}-4${hex(3)}-a${hex(3)}-${hex(12)}';
  }
}

import 'package:flutter_test/flutter_test.dart';
import 'package:school_app/core/network/api_client.dart';
import 'package:school_app/core/storage/offline_store.dart';
import 'package:school_app/services/sync_service.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'support/fake_dio.dart';
import 'support/fake_token_store.dart';

void main() {
  setUp(() {
    SharedPreferences.setMockInitialValues({});
  });

  test('queueAttendance enqueues one item per student', () async {
    final store = FakeTokenStore(token: 't');
    final api = ApiClient(store, dio: fakeDio(FakeAdapter((_) => jsonResponse({}))));
    final service = SyncService(api, OfflineStore());

    await service.queueAttendance(
      classSectionId: 1,
      attendanceDate: '2026-10-08',
      period: 'daily',
      records: [
        {'student_id': 10, 'status': 'present'},
        {'student_id': 11, 'status': 'absent', 'absence_reason': 'مرض'},
      ],
    );

    expect(await service.pendingCount(), 2);
    final items = await OfflineStore().pending();
    expect(items.first['type'], 'attendance.register');
    expect(items.first['payload']['student_id'], 10);
  });

  test('flush posts the batch then clears the queue', () async {
    final store = FakeTokenStore(token: 't');
    final adapter = FakeAdapter((_) => jsonResponse({
          'data': {'applied_count': 2},
        }, statusCode: 201));
    final service = SyncService(ApiClient(store, dio: fakeDio(adapter)), OfflineStore());

    await service.queueAttendance(
      classSectionId: 1,
      attendanceDate: '2026-10-08',
      period: 'daily',
      records: [
        {'student_id': 10, 'status': 'present'},
        {'student_id': 11, 'status': 'present'},
      ],
    );

    final applied = await service.flush();

    expect(applied, 2);
    expect(await service.pendingCount(), 0);
    expect(adapter.lastRequest!.path, 'sync/push');
  });
}

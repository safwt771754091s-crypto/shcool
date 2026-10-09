import 'package:flutter_test/flutter_test.dart';
import 'package:school_app/core/network/api_client.dart';
import 'package:school_app/services/portal_service.dart';
import 'package:school_app/services/teacher_service.dart';

import 'support/fake_dio.dart';
import 'support/fake_token_store.dart';

void main() {
  test('parentChildren() maps the portal envelope', () async {
    final adapter = FakeAdapter((_) => jsonResponse({
          'data': {
            'guardian': {'id': 1, 'full_name': 'ولي الأمر', 'relation': 'father'},
            'children': [
              {
                'student_id': 7,
                'full_name': 'الطالب',
                'student_number': 'S-7',
                'class': 'الأول',
                'section': 'أ',
                'attendance': {
                  'total': 20,
                  'present': 18,
                  'absent': 1,
                  'late': 1,
                  'rate': 95.0,
                },
                'result': {'overall_average': 88.5},
                'balance': {'total_due': 250.0, 'by_currency': {'SAR': 250.0}},
              }
            ],
          },
        }));
    final store = FakeTokenStore(token: 't');
    final service = PortalService(ApiClient(store, dio: fakeDio(adapter)));

    final children = await service.parentChildren();

    expect(children, hasLength(1));
    expect(children.first.fullName, 'الطالب');
    expect(children.first.attendance.rate, 95.0);
    expect(children.first.gradeAverage, 88.5);
    expect(children.first.balance, 250.0);
  });

  test('studentDashboard() returns null when unlinked', () async {
    final adapter = FakeAdapter((_) => jsonResponse({'data': null}));
    final store = FakeTokenStore(token: 't');
    final service = PortalService(ApiClient(store, dio: fakeDio(adapter)));

    expect(await service.studentDashboard(), isNull);
  });

  test('teacher dashboard parses aggregate counters', () async {
    final adapter = FakeAdapter((_) => jsonResponse({
          'data': {
            'teacher': {'id': 3, 'full_name': 'الأستاذ', 'employee_number': 'T-3'},
            'assignments': {
              'total': 4,
              'weekly_periods': 22,
              'subjects': ['رياضيات'],
              'sections': ['أ', 'ب'],
              'homeroom': 1,
              'items': [],
            },
            'preparations': {'pending': 2, 'approved': 5},
            'attendance': {'sessions_taken': 30, 'sessions_today': 2},
            'exams': {'created': 3, 'upcoming': []},
          },
        }));
    final store = FakeTokenStore(token: 't');
    final service = TeacherService(ApiClient(store, dio: fakeDio(adapter)));

    final dashboard = await service.dashboard();

    expect(dashboard, isNotNull);
    expect(dashboard!.fullName, 'الأستاذ');
    expect(dashboard.weeklyPeriods, 22);
    expect(dashboard.sections, 2);
    expect(dashboard.subjects, 1);
    expect(dashboard.pendingPreparations, 2);
    expect(dashboard.sessionsTaken, 30);
  });
}

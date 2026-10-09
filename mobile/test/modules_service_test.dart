import 'package:flutter_test/flutter_test.dart';
import 'package:school_app/core/network/api_client.dart';
import 'package:school_app/services/academic_service.dart';
import 'package:school_app/services/student_service.dart';
import 'package:school_app/services/records_service.dart';
import 'package:school_app/services/finance_service.dart';
import 'package:school_app/services/competition_service.dart';
import 'package:school_app/services/notification_service.dart';

import 'support/fake_dio.dart';
import 'support/fake_token_store.dart';

void main() {
  test('AcademicService.classes() parses nested sections', () async {
    final adapter = FakeAdapter((_) => jsonResponse({
          'data': [
            {
              'id': 1,
              'name': 'الصف الأول',
              'grade': 1,
              'sections': [
                {'id': 10, 'name': 'أ', 'capacity': 30, 'room': '101'},
              ],
            },
          ],
        }));
    final svc =
        AcademicService(ApiClient(FakeTokenStore(), dio: fakeDio(adapter)));
    final classes = await svc.classes();

    expect(classes, hasLength(1));
    expect(classes.first.sections.single.name, 'أ');
    expect(classes.first.sections.single.room, '101');
  });

  test('AcademicService.createSubject() posts name and code', () async {
    final adapter = FakeAdapter((_) => jsonResponse({
          'data': {'id': 3, 'name': 'رياضيات', 'code': 'MATH'},
        }, statusCode: 201));
    final svc =
        AcademicService(ApiClient(FakeTokenStore(), dio: fakeDio(adapter)));
    final subject =
        await svc.createSubject(name: 'رياضيات', code: 'MATH');

    expect(subject.code, 'MATH');
    final sent = adapter.lastRequest!.data as Map<String, dynamic>;
    expect(sent['name'], 'رياضيات');
    expect(sent['code'], 'MATH');
  });

  test('StudentService.students() unwraps a paginated list', () async {
    final adapter = FakeAdapter((_) => jsonResponse({
          'data': {
            'data': [
              {
                'id': 7,
                'full_name': 'أحمد',
                'student_number': 'S-7',
                'status': 'enrolled',
                'section': {'name': 'أ', 'school_class': {'name': 'الأول'}},
              },
            ],
          },
        }));
    final svc =
        StudentService(ApiClient(FakeTokenStore(), dio: fakeDio(adapter)));
    final students = await svc.students();

    expect(students.single.fullName, 'أحمد');
    expect(students.single.statusLabel, 'مُسجَّل');
    expect(students.single.className, 'الأول');
  });

  test('RecordsService.attendanceReport() parses the summary rows', () async {
    final adapter = FakeAdapter((_) => jsonResponse({
          'data': [
            {
              'student_id': 7,
              'full_name': 'أحمد',
              'total': 20,
              'present': 18,
              'absent': 2,
              'rate': 90,
            },
          ],
        }));
    final svc =
        RecordsService(ApiClient(FakeTokenStore(), dio: fakeDio(adapter)));
    final rows =
        await svc.attendanceReport(sectionId: 1, from: '2026-01-01', to: '2026-01-31');

    expect(rows.single.present, 18);
    expect(rows.single.rate, 90);
  });

  test('RecordsService.sectionResultSheet() parses ranked sheets', () async {
    final adapter = FakeAdapter((_) => jsonResponse({
          'data': [
            {
              'student_id': 7,
              'full_name': 'أحمد',
              'overall_average': 88.5,
              'rank': 1,
              'subjects': [
                {'subject': 'رياضيات', 'average': 90, 'passed': true},
              ],
            },
          ],
        }));
    final svc =
        RecordsService(ApiClient(FakeTokenStore(), dio: fakeDio(adapter)));
    final sheets = await svc.sectionResultSheet(sectionId: 1);

    expect(sheets.single.rank, 1);
    expect(sheets.single.overallAverage, 88.5);
    expect(sheets.single.subjects.single.subject, 'رياضيات');
  });

  test('FinanceService.summary() parses totals and breakdown', () async {
    final adapter = FakeAdapter((_) => jsonResponse({
          'data': {
            'invoiced': 1000,
            'collected': 600,
            'outstanding': 400,
            'by_method': {'cash': 600},
            'invoices': {'unpaid': 2},
          },
        }));
    final svc =
        FinanceService(ApiClient(FakeTokenStore(), dio: fakeDio(adapter)));
    final summary = await svc.summary();

    expect(summary.invoiced, 1000);
    expect(summary.outstanding, 400);
    expect(summary.byMethod['cash'], 600);
    expect(summary.invoices['unpaid'], 2);
  });

  test('CompetitionService.leaderboard() parses entries and delta', () async {
    final adapter = FakeAdapter((_) => jsonResponse({
          'data': [
            {
              'scope_type': 'school',
              'scope_id': 4,
              'name': 'مدرسة النجاح',
              'total_points': 92.5,
              'rank': 2,
              'previous_rank': 5,
            },
          ],
        }));
    final svc =
        CompetitionService(ApiClient(FakeTokenStore(), dio: fakeDio(adapter)));
    final board = await svc.leaderboard(periodId: 1, scope: 'school');

    expect(board.single.name, 'مدرسة النجاح');
    expect(board.single.rank, 2);
    expect(board.single.rankDelta, 3);
    expect(board.single.scopeLabel, 'مدرسة');
  });

  test('NotificationService.inbox() and preferences() parse payloads', () async {
    final adapter = FakeAdapter((options) {
      if (options.path.endsWith('preferences')) {
        return jsonResponse({
          'data': [
            {'channel': 'sms', 'is_enabled': true},
            {'channel': 'whatsapp', 'is_enabled': false},
          ],
        });
      }
      return jsonResponse({
        'data': [
          {
            'id': 'uuid-1',
            'read_at': null,
            'data': {'title': 'تنبيه', 'body': 'نص التنبيه'},
          },
        ],
        'unread': 1,
      });
    });
    final svc =
        NotificationService(ApiClient(FakeTokenStore(), dio: fakeDio(adapter)));

    final inbox = await svc.inbox();
    expect(inbox.single.isUnread, isTrue);
    expect(inbox.single.title, 'تنبيه');

    final prefs = await svc.preferences();
    expect(prefs, hasLength(2));
    expect(prefs.first.label, 'الرسائل النصية (SMS)');
    expect(prefs[1].isEnabled, isFalse);
  });
}

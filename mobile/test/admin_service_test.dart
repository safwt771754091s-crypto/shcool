import 'package:flutter_test/flutter_test.dart';
import 'package:school_app/core/network/api_client.dart';
import 'package:school_app/services/admin_service.dart';

import 'support/fake_dio.dart';
import 'support/fake_token_store.dart';

void main() {
  test('tree() parses the nested hierarchy', () async {
    final adapter = FakeAdapter((_) => jsonResponse({
          'data': [
            {
              'id': 1,
              'type': 'ministry',
              'name': 'وزارة التربية',
              'code': 'MOE',
              'level': 1,
              'children': [
                {
                  'id': 2,
                  'parent_id': 1,
                  'type': 'governorate',
                  'name': 'محافظة بغداد',
                  'code': 'GOV-BGW',
                  'level': 2,
                  'children': const [],
                },
              ],
            },
          ],
        }));

    final service = AdminService(ApiClient(FakeTokenStore(), dio: fakeDio(adapter)));
    final roots = await service.tree();

    expect(roots, hasLength(1));
    expect(roots.first.name, 'وزارة التربية');
    expect(roots.first.children, hasLength(1));
    expect(roots.first.children.first.typeLabel, 'محافظة');
    expect(roots.first.childType, 'governorate');
  });

  test('createOrganization() posts parent, type, name and code', () async {
    final adapter = FakeAdapter((_) => jsonResponse({
          'data': {
            'id': 9,
            'type': 'school',
            'name': 'مدرسة النور',
            'code': 'SCH-9',
            'level': 4,
          },
        }, statusCode: 201));

    final service = AdminService(ApiClient(FakeTokenStore(), dio: fakeDio(adapter)));
    final org = await service.createOrganization(
      parentId: 3,
      type: 'school',
      name: 'مدرسة النور',
      code: 'SCH-9',
    );

    expect(org.id, 9);
    expect(org.isSchool, isTrue);

    final sent = adapter.lastRequest!.data as Map<String, dynamic>;
    expect(sent['parent_id'], 3);
    expect(sent['type'], 'school');
    expect(sent['name'], 'مدرسة النور');
    expect(sent['code'], 'SCH-9');
  });

  test('apps() and createApp() map the registry', () async {
    final adapter = FakeAdapter((options) {
      if (options.method == 'POST') {
        return jsonResponse({
          'data': {'id': 5, 'name': 'المكتبة', 'slug': 'library'},
        }, statusCode: 201);
      }
      return jsonResponse({
        'data': [
          {'id': 1, 'name': 'المكتبة', 'slug': 'library', 'instances_count': 2},
        ],
      });
    });

    final service = AdminService(ApiClient(FakeTokenStore(), dio: fakeDio(adapter)));

    final apps = await service.apps();
    expect(apps.single.name, 'المكتبة');
    expect(apps.single.instancesCount, 2);

    final created = await service.createApp(name: 'المكتبة', slug: 'library');
    expect(created.slug, 'library');
  });

  test('monitoringOverview() parses totals and per-school rows', () async {
    final adapter = FakeAdapter((_) => jsonResponse({
          'data': {
            'totals': {'schools': 2, 'students': 15, 'teachers': 8},
            'per_school': [
              {'id': 4, 'name': 'مدرسة النجاح', 'code': 'SCH-1', 'students': 10, 'teachers': 5},
            ],
            'generated_at': '2026-10-09T00:00:00+00:00',
          },
        }));

    final service = AdminService(ApiClient(FakeTokenStore(), dio: fakeDio(adapter)));
    final overview = await service.monitoringOverview();

    expect(overview.total('schools'), 2);
    expect(overview.total('students'), 15);
    expect(overview.perSchool.single.name, 'مدرسة النجاح');
    expect(overview.perSchool.single.students, 10);
  });

  test('users() parses a paginated staff list', () async {
    final adapter = FakeAdapter((_) => jsonResponse({
          'data': {
            'data': [
              {'id': 5, 'name': 'مدير', 'email': 'm@x.y', 'tenant_id': 4, 'roles': ['school_manager']},
            ],
          },
        }));

    final service = AdminService(ApiClient(FakeTokenStore(), dio: fakeDio(adapter)));
    final users = await service.users();

    expect(users.single.email, 'm@x.y');
    expect(users.single.roles, ['school_manager']);
  });

  test('createUser() posts the role and organization', () async {
    final adapter = FakeAdapter((_) => jsonResponse({
          'data': {'id': 9, 'name': 'معلم', 'email': 't@x.y', 'roles': ['teacher']},
        }, statusCode: 201));

    final service = AdminService(ApiClient(FakeTokenStore(), dio: fakeDio(adapter)));
    final created = await service.createUser(
      name: 'معلم',
      email: 't@x.y',
      password: 'password123',
      role: 'teacher',
      organizationId: 4,
    );

    expect(created.id, 9);
    final sent = adapter.lastRequest!.data as Map<String, dynamic>;
    expect(sent['role'], 'teacher');
    expect(sent['organization_id'], 4);
  });
}

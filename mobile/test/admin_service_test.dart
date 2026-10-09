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
}

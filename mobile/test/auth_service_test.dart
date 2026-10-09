import 'package:flutter_test/flutter_test.dart';
import 'package:school_app/core/network/api_client.dart';
import 'package:school_app/models/app_user.dart';
import 'package:school_app/services/auth_service.dart';

import 'support/fake_dio.dart';
import 'support/fake_token_store.dart';

void main() {
  test('login stores token and returns a session', () async {
    final adapter = FakeAdapter((options) => jsonResponse({
          'token': 'abc123',
          'token_type': 'Bearer',
          'user': {
            'id': 1,
            'name': 'مدير',
            'email': 'a@b.c',
            'roles': ['school_manager'],
            'permissions': ['students.view'],
          },
        }));
    final store = FakeTokenStore();
    final service = AuthService(
      ApiClient(store, dio: fakeDio(adapter)),
      store,
    );

    final result = await service.login('a@b.c', 'secret');

    expect(result.requiresTwoFactor, isFalse);
    expect(result.user!.name, 'مدير');
    expect(result.user!.primaryRole, 'school_manager');
    expect(store.token, 'abc123');
    expect(adapter.lastRequest!.path, 'auth/login');
  });

  test('login returns a challenge when 2FA is required', () async {
    final adapter = FakeAdapter((_) => jsonResponse({
          'two_factor_required': true,
          'challenge_token': 'challenge-xyz',
        }));
    final store = FakeTokenStore();
    final service = AuthService(ApiClient(store, dio: fakeDio(adapter)), store);

    final result = await service.login('a@b.c', 'secret');

    expect(result.requiresTwoFactor, isTrue);
    expect(result.challengeToken, 'challenge-xyz');
    expect(store.token, isNull);
  });

  test('two-factor challenge sends the restricted token header', () async {
    final adapter = FakeAdapter((_) => jsonResponse({
          'token': 'full-token',
          'user': {'id': 2, 'name': 'x', 'email': 'x@y.z', 'roles': []},
        }));
    final store = FakeTokenStore(token: 'stale');
    final api = ApiClient(store, dio: fakeDio(adapter));
    final service = AuthService(api, store);

    await service.submitTwoFactor('challenge-xyz', '123456');

    expect(
      adapter.lastRequest!.headers['Authorization'],
      'Bearer challenge-xyz',
    );
    expect(store.token, 'full-token');
  });

  test('me() parses the user envelope', () async {
    final adapter = FakeAdapter((_) => jsonResponse({
          'data': {
            'id': 5,
            'name': 'ولي أمر',
            'email': 'p@x.y',
            'roles': ['parent'],
          },
        }));
    final store = FakeTokenStore(token: 't');
    final service = AuthService(ApiClient(store, dio: fakeDio(adapter)), store);

    final user = await service.me();

    expect(user, isA<AppUser>());
    expect(user!.primaryRole, 'parent');
  });
}

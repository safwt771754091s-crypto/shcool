import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:school_app/core/network/api_client.dart';

import 'support/fake_dio.dart';
import 'support/fake_token_store.dart';

void main() {
  test('joins base URL and path with a separating slash', () async {
    // Regression: Dio concatenates baseUrl + path verbatim, so a base ending in
    // a segment (…/api/v1) plus 'auth/login' became '…/api/v1auth/login' (404).
    final adapter = FakeAdapter((_) => jsonResponse({'ok': true}));
    final dio = Dio(BaseOptions(baseUrl: 'http://host/api/v1'))
      ..httpClientAdapter = adapter;

    final api = ApiClient(FakeTokenStore(), dio: dio);
    await api.get('auth/me');

    expect(adapter.lastRequest!.uri.toString(), 'http://host/api/v1/auth/me');
  });

  test('does not duplicate the slash when base already ends with one', () async {
    final adapter = FakeAdapter((_) => jsonResponse({'ok': true}));
    final dio = Dio(BaseOptions(baseUrl: 'http://host/api/v1/'))
      ..httpClientAdapter = adapter;

    final api = ApiClient(FakeTokenStore(), dio: dio);
    await api.get('auth/me');

    expect(adapter.lastRequest!.uri.toString(), 'http://host/api/v1/auth/me');
  });
}

import 'dart:convert';

import 'package:dio/dio.dart';

/// A Dio adapter that returns canned JSON per request path, so services can be
/// tested without a network. Records the last request for assertions.
class FakeAdapter implements HttpClientAdapter {
  FakeAdapter(this.handler);

  final ResponseBody Function(RequestOptions options) handler;
  RequestOptions? lastRequest;

  @override
  Future<ResponseBody> fetch(RequestOptions options,
      Stream<List<int>>? requestStream, Future<void>? cancelFuture) async {
    lastRequest = options;
    return handler(options);
  }

  @override
  void close({bool force = false}) {}
}

ResponseBody jsonResponse(Map<String, dynamic> body, {int statusCode = 200}) =>
    ResponseBody.fromString(
      jsonEncode(body),
      statusCode,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
      },
    );

Dio fakeDio(FakeAdapter adapter) => Dio(BaseOptions(baseUrl: 'http://test/api/v1'))
  ..httpClientAdapter = adapter;

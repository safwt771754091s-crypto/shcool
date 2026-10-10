import 'package:dio/dio.dart';

import '../config/app_config.dart';
import '../storage/token_store.dart';

/// Thrown for any non-2xx response, carrying the server message when present.
class ApiException implements Exception {
  ApiException(this.message, {this.statusCode, this.errors});

  final String message;
  final int? statusCode;
  final Map<String, dynamic>? errors;

  bool get isUnauthorized => statusCode == 401;

  @override
  String toString() => message;
}

/// Thin wrapper over Dio: injects the bearer token, unwraps Laravel's
/// envelope (`data`), and normalises error payloads.
class ApiClient {
  ApiClient(this._tokens, {Dio? dio})
      : _dio = dio ??
            Dio(BaseOptions(
              baseUrl: _normaliseBaseUrl(AppConfig.apiBaseUrl),
              connectTimeout: AppConfig.connectTimeout,
              receiveTimeout: AppConfig.receiveTimeout,
              headers: {'Accept': 'application/json'},
            )) {
    _dio.options.baseUrl = _normaliseBaseUrl(_dio.options.baseUrl);
    _dio.interceptors.add(
      InterceptorsWrapper(onRequest: (options, handler) async {
        if (!options.headers.containsKey('Authorization')) {
          final token = await _tokens.readToken();
          if (token != null) {
            options.headers['Authorization'] = 'Bearer $token';
          }
        }
        handler.next(options);
      }),
    );
  }

  final Dio _dio;
  final TokenStore _tokens;

  Dio get raw => _dio;

  Future<dynamic> get(String path, {Map<String, dynamic>? query}) =>
      _request(() => _dio.get(path, queryParameters: query));

  Future<dynamic> post(String path, {Object? data}) =>
      _request(() => _dio.post(path, data: data));

  Future<dynamic> put(String path, {Object? data}) =>
      _request(() => _dio.put(path, data: data));

  Future<dynamic> delete(String path, {Object? data}) =>
      _request(() => _dio.delete(path, data: data));

  /// Multipart upload (used by bulk Excel/CSV import).
  Future<dynamic> upload(
    String path, {
    required String field,
    required List<int> bytes,
    required String filename,
    Map<String, dynamic> fields = const {},
  }) =>
      _request(() => _dio.post(
            path,
            data: FormData.fromMap({
              ...fields,
              field: MultipartFile.fromBytes(bytes, filename: filename),
            }),
          ));

  /// Download a binary body (used by report exports: PDF/XLSX/CSV).
  Future<List<int>> download(String path, {Map<String, dynamic>? query}) async {
    try {
      final response = await _dio.get<List<int>>(
        path,
        queryParameters: query,
        options: Options(responseType: ResponseType.bytes),
      );
      return response.data ?? const [];
    } on DioException catch (e) {
      throw _toApiException(e);
    }
  }

  /// POST with an explicit bearer token, bypassing the stored one. Used for the
  /// restricted 2FA challenge token.
  Future<dynamic> postWithToken(String path, String token, {Object? data}) =>
      _request(() => _dio.post(
            path,
            data: data,
            options: Options(headers: {'Authorization': 'Bearer $token'}),
          ));

  Future<dynamic> _request(Future<Response<dynamic>> Function() run) async {
    try {
      final response = await run();
      return response.data;
    } on DioException catch (e) {
      throw _toApiException(e);
    }
  }

  ApiException _toApiException(DioException e) {
    final status = e.response?.statusCode;
    final body = e.response?.data;

    if (body is Map<String, dynamic>) {
      final message = body['message'];
      if (message is String && message.isNotEmpty) {
        return ApiException(message,
            statusCode: status, errors: _asMap(body['errors']));
      }
    }

    if (e.type == DioExceptionType.connectionTimeout ||
        e.type == DioExceptionType.receiveTimeout ||
        e.type == DioExceptionType.connectionError) {
      return ApiException('تعذّر الاتصال بالخادم. تحقّق من الشبكة.',
          statusCode: status);
    }

    return ApiException('حدث خطأ غير متوقّع ($status).', statusCode: status);
  }

  Map<String, dynamic>? _asMap(dynamic value) =>
      value is Map<String, dynamic> ? value : null;
}

/// Dio concatenates `baseUrl` + path with no separator, so a base URL like
/// `https://host/api/v1` and a path like `auth/login` would become
/// `.../api/v1auth/login`. Ensure the base ends with exactly one `/`.
String _normaliseBaseUrl(String raw) {
  var url = raw.trim();
  if (url.isEmpty) return url;
  if (!url.endsWith('/')) url = '$url/';
  return url;
}

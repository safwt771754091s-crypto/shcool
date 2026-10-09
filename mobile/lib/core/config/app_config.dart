/// Runtime configuration for the API client.
///
/// Override the base URL at build time, e.g.
///   flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
class AppConfig {
  static const String appName = 'منصة المدرسة الرقمية';

  /// Platform owner (مالك المنصة), shown across the app as attribution.
  static const String platformOwner = 'المهندس صفوت البريهي';
  static const String platformOwnerTitle = 'مالك المنصة';

  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000/api/v1',
  );

  static const Duration connectTimeout = Duration(seconds: 20);
  static const Duration receiveTimeout = Duration(seconds: 30);
}

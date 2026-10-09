/// Notification models: inbox items, channel status and preferences.
library;

class NotificationItem {
  NotificationItem({
    required this.id,
    this.title,
    this.body,
    this.channel,
    this.status,
    this.readAt,
    this.createdAt,
  });

  final String id;
  final String? title;
  final String? body;
  final String? channel;
  final String? status;
  final String? readAt;
  final String? createdAt;

  bool get isUnread => readAt == null;

  factory NotificationItem.fromJson(Map<String, dynamic> json) {
    final data = (json['data'] as Map?)?.cast<String, dynamic>();
    return NotificationItem(
      id: json['id'].toString(),
      title: (json['title'] ?? data?['title'])?.toString(),
      body: (json['body'] ?? data?['body'])?.toString(),
      channel: json['channel'] as String?,
      status: json['status'] as String?,
      readAt: json['read_at'] as String?,
      createdAt: json['created_at']?.toString(),
    );
  }
}

class NotificationChannels {
  NotificationChannels({this.registered = const [], this.available = const []});

  final List<String> registered;
  final List<String> available;

  factory NotificationChannels.fromJson(Map<String, dynamic> json) =>
      NotificationChannels(
        registered: (json['registered'] as List?)
                ?.map((e) => e.toString())
                .toList() ??
            const [],
        available: (json['available'] as List?)
                ?.map((e) => e.toString())
                .toList() ??
            const [],
      );
}

class NotificationPreference {
  NotificationPreference({required this.channel, this.isEnabled = true});

  final String channel;
  final bool isEnabled;

  String get label => switch (channel) {
        'sms' => 'الرسائل النصية (SMS)',
        'whatsapp' => 'واتساب',
        'email' => 'البريد الإلكتروني',
        'push' => 'الإشعارات الفورية',
        'in_app' => 'داخل التطبيق',
        _ => channel,
      };

  factory NotificationPreference.fromJson(Map<String, dynamic> json) =>
      NotificationPreference(
        channel: json['channel']?.toString() ?? '',
        isEnabled: json['is_enabled'] != false,
      );
}

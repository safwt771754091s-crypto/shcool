import '../core/network/api_client.dart';
import '../models/notification.dart';

/// In-app notification inbox, channel status and per-user preferences.
class NotificationService {
  NotificationService(this._api);

  final ApiClient _api;

  /// Returns the inbox items; the unread count is exposed via [unreadCount].
  Future<List<NotificationItem>> inbox() async {
    final data = await _api.get('notifications/inbox') as Map<String, dynamic>;
    final payload = data['data'] as List? ?? const [];
    return payload
        .map((e) =>
            NotificationItem.fromJson((e as Map).cast<String, dynamic>()))
        .toList();
  }

  Future<void> markRead(String id) async {
    await _api.post('notifications/$id/read');
  }

  Future<NotificationChannels> channels() async {
    final data =
        await _api.get('notifications/channels') as Map<String, dynamic>;
    return NotificationChannels.fromJson(
        (data['data'] as Map).cast<String, dynamic>());
  }

  Future<List<NotificationPreference>> preferences() async {
    final data =
        await _api.get('notifications/preferences') as Map<String, dynamic>;
    final payload = data['data'] as List? ?? const [];
    return payload
        .map((e) => NotificationPreference.fromJson(
            (e as Map).cast<String, dynamic>()))
        .toList();
  }

  Future<void> savePreferences(List<NotificationPreference> prefs) async {
    await _api.put('notifications/preferences', data: {
      'preferences': [
        for (final p in prefs)
          {'channel': p.channel, 'is_enabled': p.isEnabled},
      ],
    });
  }
}

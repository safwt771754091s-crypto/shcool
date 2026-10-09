import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

/// Durable queue of offline mutations (العمل دون إنترنت) awaiting sync.
///
/// Each entry is a `sync/push` item: `{type, client_id, payload}`. Entries are
/// grouped into a batch on flush so the server can dedupe by `client_batch_id`.
class OfflineStore {
  static const _queueKey = 'offline_queue_v1';

  Future<List<Map<String, dynamic>>> pending() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_queueKey);
    if (raw == null || raw.isEmpty) return [];
    return (jsonDecode(raw) as List).map((e) => (e as Map).cast<String, dynamic>()).toList();
  }

  Future<int> count() async => (await pending()).length;

  Future<void> enqueue(Map<String, dynamic> item) async {
    final items = await pending()..add(item);
    await _write(items);
  }

  Future<void> removeWhere(bool Function(Map<String, dynamic>) test) async {
    final items = await pending()..removeWhere(test);
    await _write(items);
  }

  Future<void> clear() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_queueKey);
  }

  Future<void> _write(List<Map<String, dynamic>> items) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_queueKey, jsonEncode(items));
  }
}

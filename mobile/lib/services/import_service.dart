import '../core/network/api_client.dart';
import '../models/import_template.dart';

/// Bulk import via Excel/CSV templates.
class ImportService {
  ImportService(this._api);

  final ApiClient _api;

  Future<List<ImportTemplate>> templates() async {
    final data = await _api.get('imports/templates') as Map<String, dynamic>;
    final payload = data['data'] as List? ?? const [];
    return payload
        .map((e) => ImportTemplate.fromJson((e as Map).cast<String, dynamic>()))
        .toList();
  }

  /// Import an uploaded file's bytes for the given template [type].
  Future<Map<String, dynamic>> upload({
    required String type,
    required List<int> bytes,
    required String filename,
  }) async {
    final data = await _api.upload('imports',
        field: 'file', bytes: bytes, filename: filename) as Map<String, dynamic>;
    return (data['data'] as Map?)?.cast<String, dynamic>() ?? const {};
  }
}

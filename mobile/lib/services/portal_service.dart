import '../core/network/api_client.dart';
import '../models/portal.dart';

class PortalService {
  PortalService(this._api);

  final ApiClient _api;

  /// Dashboard for the signed-in parent: their children with summaries.
  Future<List<ChildSummary>> parentChildren() async {
    final data = await _api.get('portal/parent') as Map<String, dynamic>;
    final payload = data['data'] as Map<String, dynamic>?;
    final children = payload?['children'] as List? ?? const [];
    return children
        .map((e) => ChildSummary.fromJson((e as Map).cast<String, dynamic>()))
        .toList();
  }

  /// Dashboard for the signed-in student. Returns null when the account is not
  /// linked to a student record.
  Future<ChildSummary?> studentDashboard() async {
    final data = await _api.get('portal/student') as Map<String, dynamic>;
    final payload = data['data'];
    if (payload is! Map) return null;
    return ChildSummary.fromJson(payload.cast<String, dynamic>());
  }
}

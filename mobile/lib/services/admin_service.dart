import '../core/network/api_client.dart';
import '../models/organization.dart';

/// Owner/administrator operations: the administrative hierarchy and the
/// mini-app registry. Requires the corresponding permissions (the platform
/// owner holds all of them).
class AdminService {
  AdminService(this._api);

  final ApiClient _api;

  /// The full hierarchy tree (ministry -> ... -> branch).
  Future<List<Organization>> tree() async {
    final data = await _api.get('organizations/tree') as Map<String, dynamic>;
    final payload = data['data'] as List? ?? const [];
    return payload
        .map((e) => Organization.fromJson((e as Map).cast<String, dynamic>()))
        .toList();
  }

  /// Flat list, optionally filtered by type.
  Future<List<Organization>> organizations({String? type}) async {
    final data = await _api.get('organizations',
        query: type == null ? null : {'type': type}) as Map<String, dynamic>;
    final payload = data['data'] as List? ?? const [];
    return payload
        .map((e) => Organization.fromJson((e as Map).cast<String, dynamic>()))
        .toList();
  }

  /// Create an organization of [type] under [parentId].
  Future<Organization> createOrganization({
    required int parentId,
    required String type,
    required String name,
    required String code,
  }) async {
    final data = await _api.post('organizations', data: {
      'parent_id': parentId,
      'type': type,
      'name': name,
      'code': code,
    }) as Map<String, dynamic>;
    return Organization.fromJson((data['data'] as Map).cast<String, dynamic>());
  }

  Future<List<MiniApp>> apps() async {
    final data = await _api.get('apps') as Map<String, dynamic>;
    final payload = data['data'] as List? ?? const [];
    return payload
        .map((e) => MiniApp.fromJson((e as Map).cast<String, dynamic>()))
        .toList();
  }

  Future<MiniApp> createApp({
    required String name,
    required String slug,
    String? category,
    String? description,
  }) async {
    final data = await _api.post('apps', data: {
      'name': name,
      'slug': slug,
      if (category != null && category.isNotEmpty) 'category': category,
      if (description != null && description.isNotEmpty) 'description': description,
    }) as Map<String, dynamic>;
    return MiniApp.fromJson((data['data'] as Map).cast<String, dynamic>());
  }

  /// Publish an app to an organization.
  Future<void> publishApp({
    required int appId,
    required int organizationId,
  }) async {
    await _api.post('apps/$appId/publish', data: {
      'organization_id': organizationId,
    });
  }
}

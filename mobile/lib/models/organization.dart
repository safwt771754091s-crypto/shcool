/// A node in the administrative hierarchy:
/// ministry -> governorate -> directorate -> school -> branch.
class Organization {
  Organization({
    required this.id,
    required this.type,
    required this.name,
    required this.code,
    this.parentId,
    this.level = 1,
    this.isActive = true,
    this.children = const [],
  });

  final int id;
  final String type;
  final String name;
  final String code;
  final int? parentId;
  final int level;
  final bool isActive;
  final List<Organization> children;

  bool get isSchool => type == 'school';

  static const Map<String, String> typeLabels = {
    'ministry': 'وزارة',
    'governorate': 'محافظة',
    'directorate': 'مديرية',
    'school': 'مدرسة',
    'branch': 'فرع',
  };

  /// The level that may be created directly beneath this one, or null at the
  /// bottom of the hierarchy.
  String? get childType => switch (type) {
        'ministry' => 'governorate',
        'governorate' => 'directorate',
        'directorate' => 'school',
        'school' => 'branch',
        _ => null,
      };

  String get typeLabel => typeLabels[type] ?? type;

  factory Organization.fromJson(Map<String, dynamic> json) => Organization(
        id: json['id'] as int,
        type: (json['type'] ?? '') as String,
        name: (json['name'] ?? '') as String,
        code: (json['code'] ?? '') as String,
        parentId: json['parent_id'] as int?,
        level: (json['level'] ?? 1) as int,
        isActive: json['is_active'] != false,
        children: (json['children'] as List?)
                ?.map((e) => Organization.fromJson((e as Map).cast<String, dynamic>()))
                .toList() ??
            const [],
      );
}

/// A mini-app template that the platform can publish to an organization.
class MiniApp {
  MiniApp({
    required this.id,
    required this.name,
    required this.slug,
    this.category,
    this.description,
    this.icon,
    this.color,
    this.instancesCount = 0,
  });

  final int id;
  final String name;
  final String slug;
  final String? category;
  final String? description;
  final String? icon;
  final String? color;
  final int instancesCount;

  factory MiniApp.fromJson(Map<String, dynamic> json) => MiniApp(
        id: json['id'] as int,
        name: (json['name'] ?? '') as String,
        slug: (json['slug'] ?? '') as String,
        category: json['category'] as String?,
        description: json['description'] as String?,
        icon: json['icon'] as String?,
        color: json['color'] as String?,
        instancesCount: (json['instances_count'] ?? 0) as int,
      );
}

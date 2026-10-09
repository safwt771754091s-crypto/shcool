class AppUser {
  AppUser({
    required this.id,
    required this.name,
    required this.email,
    this.phone,
    this.tenantId,
    this.isPlatformAdmin = false,
    this.twoFactorEnabled = false,
    this.roles = const [],
    this.permissions = const [],
  });

  final int id;
  final String name;
  final String email;
  final String? phone;
  final int? tenantId;
  final bool isPlatformAdmin;
  final bool twoFactorEnabled;
  final List<String> roles;
  final List<String> permissions;

  String get primaryRole => roles.isEmpty ? 'user' : roles.first;

  bool can(String permission) => permissions.contains(permission);

  factory AppUser.fromJson(Map<String, dynamic> json) => AppUser(
        id: json['id'] as int,
        name: (json['name'] ?? '') as String,
        email: (json['email'] ?? '') as String,
        phone: json['phone'] as String?,
        tenantId: json['tenant_id'] as int?,
        isPlatformAdmin: json['is_platform_admin'] == true,
        twoFactorEnabled: json['two_factor_enabled'] == true,
        roles: _stringList(json['roles']),
        permissions: _stringList(json['permissions']),
      );

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'email': email,
        'phone': phone,
        'tenant_id': tenantId,
        'is_platform_admin': isPlatformAdmin,
        'two_factor_enabled': twoFactorEnabled,
        'roles': roles,
        'permissions': permissions,
      };

  static List<String> _stringList(dynamic value) =>
      value is List ? value.map((e) => e.toString()).toList() : const [];
}

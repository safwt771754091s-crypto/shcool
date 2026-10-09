/// Read-only, platform-wide roll-up for the Minister of Education monitoring
/// view (رابط المراقبة الشامل).
class MonitoringOverview {
  MonitoringOverview({
    required this.totals,
    required this.perSchool,
    this.generatedAt,
  });

  final Map<String, int> totals;
  final List<SchoolSnapshot> perSchool;
  final String? generatedAt;

  int total(String key) => totals[key] ?? 0;

  factory MonitoringOverview.fromJson(Map<String, dynamic> json) =>
      MonitoringOverview(
        totals: (json['totals'] as Map?)
                ?.map((k, v) => MapEntry(k.toString(), (v as num?)?.toInt() ?? 0)) ??
            const {},
        perSchool: (json['per_school'] as List?)
                ?.map((e) => SchoolSnapshot.fromJson((e as Map).cast<String, dynamic>()))
                .toList() ??
            const [],
        generatedAt: json['generated_at'] as String?,
      );
}

/// One school's counts within the monitoring roll-up.
class SchoolSnapshot {
  SchoolSnapshot({
    required this.id,
    required this.name,
    required this.code,
    required this.students,
    required this.teachers,
  });

  final int id;
  final String name;
  final String code;
  final int students;
  final int teachers;

  factory SchoolSnapshot.fromJson(Map<String, dynamic> json) => SchoolSnapshot(
        id: (json['id'] as num).toInt(),
        name: (json['name'] ?? '') as String,
        code: (json['code'] ?? '') as String,
        students: (json['students'] as num?)?.toInt() ?? 0,
        teachers: (json['teachers'] as num?)?.toInt() ?? 0,
      );
}

/// A staff account managed from the dashboard (distinct from the signed-in
/// [AppUser], which also carries permissions).
class StaffUser {
  StaffUser({
    required this.id,
    required this.name,
    required this.email,
    this.phone,
    this.tenantId,
    this.isActive = true,
    this.roles = const [],
  });

  final int id;
  final String name;
  final String email;
  final String? phone;
  final int? tenantId;
  final bool isActive;
  final List<String> roles;

  factory StaffUser.fromJson(Map<String, dynamic> json) => StaffUser(
        id: (json['id'] as num).toInt(),
        name: (json['name'] ?? '') as String,
        email: (json['email'] ?? '') as String,
        phone: json['phone'] as String?,
        tenantId: (json['tenant_id'] as num?)?.toInt(),
        isActive: json['is_active'] != false,
        roles: json['roles'] is List
            ? (json['roles'] as List).map((e) => e.toString()).toList()
            : const [],
      );
}

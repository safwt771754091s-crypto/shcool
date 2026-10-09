import '../core/network/api_client.dart';

/// Consolidated teacher workspace (لوحة المعلم).
class TeacherDashboard {
  TeacherDashboard({
    required this.teacher,
    required this.assignments,
    required this.preparations,
    required this.attendance,
    required this.exams,
  });

  final Map<String, dynamic> teacher;
  final Map<String, dynamic> assignments;
  final Map<String, dynamic> preparations;
  final Map<String, dynamic> attendance;
  final Map<String, dynamic> exams;

  String get fullName => (teacher['full_name'] ?? '') as String;

  int get weeklyPeriods => (assignments['weekly_periods'] ?? 0) as int;
  int get sections => (assignments['sections'] as List?)?.length ?? 0;
  int get subjects => (assignments['subjects'] as List?)?.length ?? 0;
  int get sessionsTaken => (attendance['sessions_taken'] ?? 0) as int;
  int get pendingPreparations => (preparations['pending'] ?? 0) as int;

  List<Map<String, dynamic>> get upcomingExams =>
      (exams['upcoming'] as List?)
          ?.map((e) => (e as Map).cast<String, dynamic>())
          .toList() ??
      const [];

  factory TeacherDashboard.fromJson(Map<String, dynamic> json) => TeacherDashboard(
        teacher: (json['teacher'] as Map?)?.cast<String, dynamic>() ?? const {},
        assignments:
            (json['assignments'] as Map?)?.cast<String, dynamic>() ?? const {},
        preparations:
            (json['preparations'] as Map?)?.cast<String, dynamic>() ?? const {},
        attendance:
            (json['attendance'] as Map?)?.cast<String, dynamic>() ?? const {},
        exams: (json['exams'] as Map?)?.cast<String, dynamic>() ?? const {},
      );
}

class TeacherService {
  TeacherService(this._api);

  final ApiClient _api;

  /// Returns the dashboard, or null when the account has no teacher profile.
  Future<TeacherDashboard?> dashboard() async {
    final data = await _api.get('teachers/dashboard') as Map<String, dynamic>;
    final payload = data['data'];
    if (payload is! Map) return null;
    return TeacherDashboard.fromJson(payload.cast<String, dynamic>());
  }

  Future<List<Map<String, dynamic>>> myAssignments() async {
    final data = await _api.get('teachers/my-assignments') as Map<String, dynamic>;
    final payload = data['data'] as List? ?? const [];
    return payload.map((e) => (e as Map).cast<String, dynamic>()).toList();
  }
}

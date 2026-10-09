import '../core/network/api_client.dart';
import '../models/teacher.dart';

/// Teacher roster management (list/create/update/assign) for administrators.
class StaffService {
  StaffService(this._api);

  final ApiClient _api;

  Future<List<Teacher>> teachers({String? status, String? search}) async {
    final data = await _api.get('teachers', query: {
      'status': ?status,
      'search': ?search,
    }) as Map<String, dynamic>;
    final payload = data['data'];
    final rows = (payload is Map && payload['data'] is List)
        ? payload['data'] as List
        : payload as List? ?? const [];
    return rows
        .map((e) => Teacher.fromJson((e as Map).cast<String, dynamic>()))
        .toList();
  }

  Future<Teacher> createTeacher({
    required String fullName,
    required String employeeNumber,
    String? gender,
    String? specialization,
    String? jobTitle,
    String? qualification,
    String? phone,
    String? email,
  }) async {
    final data = await _api.post('teachers', data: {
      'full_name': fullName,
      'employee_number': employeeNumber,
      'gender': ?gender,
      'specialization': ?specialization,
      'job_title': ?jobTitle,
      'qualification': ?qualification,
      'phone': ?phone,
      'email': ?email,
    }) as Map<String, dynamic>;
    return Teacher.fromJson((data['data'] as Map).cast<String, dynamic>());
  }

  Future<TeachingAssignment> assign({
    required int teacherId,
    required int subjectId,
    required int sectionId,
    int? academicYearId,
    int? weeklyPeriods,
    bool isHomeroom = false,
  }) async {
    final data = await _api.post('teachers/$teacherId/assign', data: {
      'subject_id': subjectId,
      'class_section_id': sectionId,
      'academic_year_id': ?academicYearId,
      'weekly_periods': ?weeklyPeriods,
      'is_homeroom': isHomeroom,
    }) as Map<String, dynamic>;
    return TeachingAssignment.fromJson(
        (data['data'] as Map).cast<String, dynamic>());
  }
}

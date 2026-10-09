import '../core/network/api_client.dart';
import '../models/student.dart';

/// Students, admissions and teacher roster.
class StudentService {
  StudentService(this._api);

  final ApiClient _api;

  /// Paginated list. [status] and [search] filter server-side.
  Future<List<Student>> students({
    int? sectionId,
    String? status,
    String? search,
  }) async {
    final data = await _api.get('students', query: {
      'class_section_id': ?sectionId,
      'status': ?status,
      'search': ?search,
    }) as Map<String, dynamic>;
    final payload = _rows(data);
    return payload
        .map((e) => Student.fromJson((e as Map).cast<String, dynamic>()))
        .toList();
  }

  Future<Student> createStudent({
    required String fullName,
    required String studentNumber,
    int? sectionId,
    String? gender,
    String? birthDate,
    String? nationalId,
    String? phone,
    String? address,
  }) async {
    final data = await _api.post('students', data: {
      'full_name': fullName,
      'student_number': studentNumber,
      'class_section_id': ?sectionId,
      'gender': ?gender,
      'birth_date': ?birthDate,
      'national_id': ?nationalId,
      'phone': ?phone,
      'address': ?address,
    }) as Map<String, dynamic>;
    return Student.fromJson((data['data'] as Map).cast<String, dynamic>());
  }

  Future<Student> updateStudent(
    int id, {
    String? fullName,
    String? phone,
    String? address,
    String? notes,
  }) async {
    final data = await _api.put('students/$id', data: {
      'full_name': ?fullName,
      'phone': ?phone,
      'address': ?address,
      'notes': ?notes,
    }) as Map<String, dynamic>;
    return Student.fromJson((data['data'] as Map).cast<String, dynamic>());
  }

  Future<Student> changeStatus(int id, String status) async {
    final data = await _api.post('students/$id/status', data: {'status': status})
        as Map<String, dynamic>;
    return Student.fromJson((data['data'] as Map).cast<String, dynamic>());
  }

  Future<List<Admission>> admissions({String? status}) async {
    final data = await _api.get('admissions',
        query: {'status': ?status}) as Map<String, dynamic>;
    final payload = data['data'] as List? ?? const [];
    return payload
        .map((e) => Admission.fromJson((e as Map).cast<String, dynamic>()))
        .toList();
  }

  Future<Admission> createAdmission({
    required String applicantName,
    int? yearId,
    int? classId,
    String? guardianName,
    String? guardianPhone,
    String? gender,
  }) async {
    final data = await _api.post('admissions', data: {
      'applicant_name': applicantName,
      'academic_year_id': ?yearId,
      'school_class_id': ?classId,
      'guardian_name': ?guardianName,
      'guardian_phone': ?guardianPhone,
      'gender': ?gender,
    }) as Map<String, dynamic>;
    return Admission.fromJson((data['data'] as Map).cast<String, dynamic>());
  }

  Future<Admission> decideAdmission(int id,
      {required bool accepted, int? score, String? note}) async {
    final data = await _api.post('admissions/$id/decide', data: {
      'accepted': accepted,
      'score': ?score,
      'note': ?note,
    }) as Map<String, dynamic>;
    return Admission.fromJson((data['data'] as Map).cast<String, dynamic>());
  }

  /// Laravel `paginate()` wraps rows in `data.data`; plain lists use `data`.
  List<dynamic> _rows(Map<String, dynamic> data) {
    final payload = data['data'];
    if (payload is Map && payload['data'] is List) {
      return payload['data'] as List;
    }
    return payload as List? ?? const [];
  }
}

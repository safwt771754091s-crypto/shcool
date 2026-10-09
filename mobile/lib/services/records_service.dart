import '../core/network/api_client.dart';
import '../models/attendance.dart';
import '../models/exam.dart';
import '../models/grade.dart';
import '../models/register.dart';
import '../models/student.dart';

/// Attendance reports and exam/grade/result-sheet operations.
class RecordsService {
  RecordsService(this._api);

  final ApiClient _api;

  /// The students of a section, used to seed a fresh register.
  Future<List<Student>> sectionStudents(int sectionId) async {
    final data = await _api.get('students', query: {
      'class_section_id': sectionId,
    }) as Map<String, dynamic>;
    final payload = data['data'];
    final rows = (payload is Map && payload['data'] is List)
        ? payload['data'] as List
        : payload as List? ?? const [];
    return rows
        .map((e) => Student.fromJson((e as Map).cast<String, dynamic>()))
        .toList();
  }

  /// A previously-saved register for a section on a date, or null.
  Future<AttendanceSession?> attendanceSession({
    required int sectionId,
    required String date,
    String period = 'daily',
  }) async {
    final data = await _api.get('attendance/session', query: {
      'class_section_id': sectionId,
      'attendance_date': date,
      'period': period,
    }) as Map<String, dynamic>;
    final payload = data['data'];
    if (payload is! Map) return null;
    return AttendanceSession.fromJson(payload.cast<String, dynamic>());
  }

  Future<void> takeRegister({
    required int sectionId,
    required String date,
    required List<RegisterEntry> entries,
    String period = 'daily',
    String? notes,
  }) async {
    await _api.post('attendance/register', data: {
      'class_section_id': sectionId,
      'attendance_date': date,
      'period': period,
      'notes': ?notes,
      'entries': [for (final e in entries) e.toPayload()],
    });
  }

  Future<List<AttendanceReportRow>> attendanceReport({
    required int sectionId,
    required String from,
    required String to,
  }) async {
    final data = await _api.get('attendance/report', query: {
      'class_section_id': sectionId,
      'from': from,
      'to': to,
    }) as Map<String, dynamic>;
    final payload = data['data'] as List? ?? const [];
    return payload
        .map((e) =>
            AttendanceReportRow.fromJson((e as Map).cast<String, dynamic>()))
        .toList();
  }

  Future<List<AbsenceAlert>> absenceAlerts({
    required String from,
    required String to,
    int threshold = 3,
  }) async {
    final data = await _api.get('attendance/absence-alerts', query: {
      'from': from,
      'to': to,
      'threshold': threshold,
    }) as Map<String, dynamic>;
    final payload = data['data'] as List? ?? const [];
    return payload
        .map((e) => AbsenceAlert.fromJson((e as Map).cast<String, dynamic>()))
        .toList();
  }

  Future<List<Exam>> exams({
    int? sectionId,
    int? subjectId,
    int? termId,
  }) async {
    final data = await _api.get('exams', query: {
      'class_section_id': ?sectionId,
      'subject_id': ?subjectId,
      'term_id': ?termId,
    }) as Map<String, dynamic>;
    final payload = data['data'] as List? ?? const [];
    return payload
        .map((e) => Exam.fromJson((e as Map).cast<String, dynamic>()))
        .toList();
  }

  Future<Exam> createExam({
    required String title,
    required int subjectId,
    required int sectionId,
    int? termId,
    String? type,
    String? heldOn,
    double? maxMark,
    double? passMark,
  }) async {
    final data = await _api.post('exams', data: {
      'title': title,
      'subject_id': subjectId,
      'class_section_id': sectionId,
      'term_id': ?termId,
      'type': ?type,
      'held_on': ?heldOn,
      'max_mark': ?maxMark,
      'pass_mark': ?passMark,
    }) as Map<String, dynamic>;
    return Exam.fromJson((data['data'] as Map).cast<String, dynamic>());
  }

  Future<void> recordGrades({
    required int examId,
    required List<Map<String, dynamic>> rows,
  }) async {
    await _api.post('exams/$examId/grades', data: {'rows': rows});
  }

  /// The exam with its already-recorded grades (used to seed the entry sheet).
  Future<List<GradeEntry>> examGrades(int examId) async {
    final data = await _api.get('exams/$examId') as Map<String, dynamic>;
    final payload = (data['data'] as Map).cast<String, dynamic>();
    final grades = payload['grades'] as List? ?? const [];
    return grades
        .map((e) => GradeEntry.fromJson((e as Map).cast<String, dynamic>()))
        .toList();
  }

  Future<void> publishExam(int examId) async {
    await _api.post('exams/$examId/publish');
  }

  /// Ranked result sheets for a whole section (each carries a `rank`).
  Future<List<ResultSheet>> sectionResultSheet({
    required int sectionId,
    int? termId,
  }) async {
    final data = await _api.get('exams/result-sheet', query: {
      'class_section_id': sectionId,
      'term_id': ?termId,
    }) as Map<String, dynamic>;
    final payload = data['data'] as List? ?? const [];
    return payload
        .map((e) => ResultSheet.fromJson((e as Map).cast<String, dynamic>()))
        .toList();
  }
}

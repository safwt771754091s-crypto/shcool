import '../core/network/api_client.dart';
import '../models/attendance.dart';
import '../models/exam.dart';

/// Attendance reports and exam/grade/result-sheet operations.
class RecordsService {
  RecordsService(this._api);

  final ApiClient _api;

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

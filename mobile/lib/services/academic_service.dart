import '../core/network/api_client.dart';
import '../models/academic.dart';

/// Academic structure: years, terms, subjects, classes and sections.
class AcademicService {
  AcademicService(this._api);

  final ApiClient _api;

  Future<List<AcademicYear>> years() async {
    final data = await _api.get('academic/years') as Map<String, dynamic>;
    return _list(data, AcademicYear.fromJson);
  }

  Future<AcademicYear> createYear({
    required String name,
    required String startsOn,
    required String endsOn,
    bool isCurrent = false,
  }) async {
    final data = await _api.post('academic/years', data: {
      'name': name,
      'starts_on': startsOn,
      'ends_on': endsOn,
      'is_current': isCurrent,
    }) as Map<String, dynamic>;
    return AcademicYear.fromJson((data['data'] as Map).cast<String, dynamic>());
  }

  Future<Term> createTerm({
    required int yearId,
    required String name,
    required int sequence,
    required String startsOn,
    required String endsOn,
    bool isCurrent = false,
  }) async {
    final data = await _api.post('academic/years/$yearId/terms', data: {
      'name': name,
      'sequence': sequence,
      'starts_on': startsOn,
      'ends_on': endsOn,
      'is_current': isCurrent,
    }) as Map<String, dynamic>;
    return Term.fromJson((data['data'] as Map).cast<String, dynamic>());
  }

  Future<List<Subject>> subjects() async {
    final data = await _api.get('academic/subjects') as Map<String, dynamic>;
    return _list(data, Subject.fromJson);
  }

  Future<Subject> createSubject({
    required String name,
    String? code,
    String? stage,
    double? passMark,
    double? maxMark,
  }) async {
    final data = await _api.post('academic/subjects', data: {
      'name': name,
      'code': ?code,
      'stage': ?stage,
      'pass_mark': ?passMark,
      'max_mark': ?maxMark,
    }) as Map<String, dynamic>;
    return Subject.fromJson((data['data'] as Map).cast<String, dynamic>());
  }

  Future<List<SchoolClass>> classes() async {
    final data = await _api.get('academic/classes') as Map<String, dynamic>;
    return _list(data, SchoolClass.fromJson);
  }

  Future<SchoolClass> createClass({
    required String name,
    required int grade,
    String? stage,
    int? branchId,
  }) async {
    final data = await _api.post('academic/classes', data: {
      'name': name,
      'grade': grade,
      'stage': ?stage,
      'branch_id': ?branchId,
    }) as Map<String, dynamic>;
    return SchoolClass.fromJson((data['data'] as Map).cast<String, dynamic>());
  }

  Future<ClassSection> createSection({
    required int classId,
    required String name,
    int? capacity,
    String? room,
  }) async {
    final data = await _api.post('academic/classes/$classId/sections', data: {
      'name': name,
      'capacity': ?capacity,
      'room': ?room,
    }) as Map<String, dynamic>;
    return ClassSection.fromJson(
        (data['data'] as Map).cast<String, dynamic>());
  }

  List<T> _list<T>(Map<String, dynamic> data, T Function(Map<String, dynamic>) f) {
    final payload = data['data'] as List? ?? const [];
    return payload.map((e) => f((e as Map).cast<String, dynamic>())).toList();
  }
}

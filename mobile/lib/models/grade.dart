/// One student's grade row for an exam's grade-entry sheet.
class GradeEntry {
  GradeEntry({
    required this.studentId,
    required this.fullName,
    this.studentNumber,
    this.mark,
    this.isAbsent = false,
    this.notes,
  });

  final int studentId;
  final String fullName;
  final String? studentNumber;
  double? mark;
  bool isAbsent;
  String? notes;

  factory GradeEntry.fromJson(Map<String, dynamic> json) {
    final student = (json['student'] as Map?)?.cast<String, dynamic>();
    return GradeEntry(
      studentId:
          ((json['student_id'] ?? student?['id']) as num?)?.toInt() ?? 0,
      fullName: (student?['full_name'] ?? json['full_name'])?.toString() ?? '',
      studentNumber:
          (student?['student_number'] ?? json['student_number'])?.toString(),
      mark: (json['mark'] as num?)?.toDouble(),
      isAbsent: json['is_absent'] == true,
      notes: json['notes']?.toString(),
    );
  }

  Map<String, dynamic> toPayload() => {
        'student_id': studentId,
        'mark': isAbsent ? null : mark,
        'is_absent': isAbsent,
        'notes': ?notes,
      };
}

/// One student's row inside an attendance register.
class RegisterEntry {
  RegisterEntry({
    required this.studentId,
    required this.fullName,
    this.studentNumber,
    this.status = 'present',
    this.lateMinutes,
    this.notes,
  });

  final int studentId;
  final String fullName;
  final String? studentNumber;
  String status;
  int? lateMinutes;
  String? notes;

  factory RegisterEntry.fromJson(Map<String, dynamic> json) {
    final student = (json['student'] as Map?)?.cast<String, dynamic>();
    return RegisterEntry(
      studentId:
          ((json['student_id'] ?? student?['id']) as num?)?.toInt() ?? 0,
      fullName: (student?['full_name'] ?? json['full_name'])?.toString() ?? '',
      studentNumber:
          (student?['student_number'] ?? json['student_number'])?.toString(),
      status: json['status']?.toString() ?? 'present',
      lateMinutes: (json['late_minutes'] as num?)?.toInt(),
      notes: json['notes']?.toString(),
    );
  }

  Map<String, dynamic> toPayload() => {
        'student_id': studentId,
        'status': status,
        'late_minutes': ?lateMinutes,
        'notes': ?notes,
      };
}

/// A saved attendance session (a section's register for one day/period).
class AttendanceSession {
  AttendanceSession({
    required this.id,
    required this.sectionId,
    this.date,
    this.period,
    this.entries = const [],
  });

  final int id;
  final int sectionId;
  final String? date;
  final String? period;
  final List<RegisterEntry> entries;

  factory AttendanceSession.fromJson(Map<String, dynamic> json) =>
      AttendanceSession(
        id: (json['id'] as num?)?.toInt() ?? 0,
        sectionId: (json['class_section_id'] as num?)?.toInt() ?? 0,
        date: json['attendance_date']?.toString(),
        period: json['period']?.toString(),
        entries: (json['attendances'] as List?)
                ?.map((e) =>
                    RegisterEntry.fromJson((e as Map).cast<String, dynamic>()))
                .toList() ??
            const [],
      );
}

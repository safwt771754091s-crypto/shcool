/// Attendance report & absence-alert models.
library;

class AttendanceReportRow {
  AttendanceReportRow({
    required this.studentId,
    required this.fullName,
    this.studentNumber,
    this.total = 0,
    this.present = 0,
    this.absent = 0,
    this.late = 0,
    this.excused = 0,
    this.rate = 0,
  });

  final int studentId;
  final String fullName;
  final String? studentNumber;
  final int total;
  final int present;
  final int absent;
  final int late;
  final int excused;
  final double rate;

  factory AttendanceReportRow.fromJson(Map<String, dynamic> json) =>
      AttendanceReportRow(
        studentId: (json['student_id'] as num).toInt(),
        fullName: json['full_name']?.toString() ?? '',
        studentNumber: json['student_number']?.toString(),
        total: (json['total'] as num?)?.toInt() ?? 0,
        present: (json['present'] as num?)?.toInt() ?? 0,
        absent: (json['absent'] as num?)?.toInt() ?? 0,
        late: (json['late'] as num?)?.toInt() ?? 0,
        excused: (json['excused'] as num?)?.toInt() ?? 0,
        rate: (json['rate'] as num?)?.toDouble() ?? 0,
      );
}

class AbsenceAlert {
  AbsenceAlert({
    required this.studentId,
    required this.fullName,
    this.studentNumber,
    this.absences = 0,
  });

  final int studentId;
  final String fullName;
  final String? studentNumber;
  final int absences;

  factory AbsenceAlert.fromJson(Map<String, dynamic> json) => AbsenceAlert(
        studentId: (json['student_id'] as num).toInt(),
        fullName: json['full_name']?.toString() ?? '',
        studentNumber: json['student_number']?.toString(),
        absences: (json['absences'] as num?)?.toInt() ?? 0,
      );
}

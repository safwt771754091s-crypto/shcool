class AttendanceSummary {
  AttendanceSummary({
    required this.total,
    required this.present,
    required this.absent,
    required this.late,
    required this.rate,
  });

  final int total;
  final int present;
  final int absent;
  final int late;
  final double rate;

  factory AttendanceSummary.fromJson(Map<String, dynamic> json) =>
      AttendanceSummary(
        total: (json['total'] ?? 0) as int,
        present: (json['present'] ?? 0) as int,
        absent: (json['absent'] ?? 0) as int,
        late: (json['late'] ?? 0) as int,
        rate: ((json['rate'] ?? 0) as num).toDouble(),
      );
}

class ChildSummary {
  ChildSummary({
    required this.studentId,
    required this.fullName,
    this.studentNumber,
    this.className,
    this.section,
    required this.attendance,
    this.balance,
    this.gradeAverage,
    this.upcomingExams = const [],
  });

  final int studentId;
  final String fullName;
  final String? studentNumber;
  final String? className;
  final String? section;
  final AttendanceSummary attendance;
  final double? balance;
  final double? gradeAverage;
  final List<Map<String, dynamic>> upcomingExams;

  factory ChildSummary.fromJson(Map<String, dynamic> json) {
    final result = json['result'];
    double? average;
    if (result is Map<String, dynamic>) {
      final a = result['overall_average'] ?? result['average'];
      if (a is num) average = a.toDouble();
    }

    double? balance;
    final b = json['balance'];
    if (b is num) {
      balance = b.toDouble();
    } else if (b is Map<String, dynamic> && b['total_due'] is num) {
      balance = (b['total_due'] as num).toDouble();
    }

    return ChildSummary(
      studentId: (json['student_id'] ?? 0) as int,
      fullName: (json['full_name'] ?? '') as String,
      studentNumber: json['student_number'] as String?,
      className: json['class'] as String?,
      section: json['section'] as String?,
      attendance: AttendanceSummary.fromJson(
          (json['attendance'] as Map?)?.cast<String, dynamic>() ?? const {}),
      balance: balance,
      gradeAverage: average,
      upcomingExams: (json['upcoming_exams'] as List?)
              ?.map((e) => (e as Map).cast<String, dynamic>())
              .toList() ??
          const [],
    );
  }
}

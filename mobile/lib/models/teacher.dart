/// Teacher & teaching-assignment models.
library;

class Teacher {
  Teacher({
    required this.id,
    required this.fullName,
    required this.employeeNumber,
    this.specialization,
    this.jobTitle,
    this.qualification,
    this.phone,
    this.email,
    this.status,
    this.assignmentsCount = 0,
  });

  final int id;
  final String fullName;
  final String employeeNumber;
  final String? specialization;
  final String? jobTitle;
  final String? qualification;
  final String? phone;
  final String? email;
  final String? status;
  final int assignmentsCount;

  String get statusLabel => switch (status) {
        'active' => 'على الملاك',
        'leave' => 'في إجازة',
        'retired' => 'متقاعد',
        'terminated' => 'منتهية خدمته',
        _ => status ?? '—',
      };

  factory Teacher.fromJson(Map<String, dynamic> json) => Teacher(
        id: (json['id'] as num).toInt(),
        fullName: json['full_name']?.toString() ?? '',
        employeeNumber: json['employee_number']?.toString() ?? '',
        specialization: json['specialization'] as String?,
        jobTitle: json['job_title'] as String?,
        qualification: json['qualification'] as String?,
        phone: json['phone'] as String?,
        email: json['email'] as String?,
        status: json['status'] as String?,
        assignmentsCount: (json['assignments_count'] as num?)?.toInt() ?? 0,
      );
}

class TeachingAssignment {
  TeachingAssignment({
    required this.id,
    this.subject,
    this.subjectId,
    this.section,
    this.sectionId,
    this.className,
    this.weeklyPeriods = 0,
    this.isHomeroom = false,
  });

  final int id;
  final String? subject;
  final int? subjectId;
  final String? section;
  final int? sectionId;
  final String? className;
  final int weeklyPeriods;
  final bool isHomeroom;

  String get label {
    final parts = [className, section].whereType<String>().toList();
    final place = parts.isEmpty ? '' : ' (${parts.join(' - ')})';
    return '${subject ?? ''}$place';
  }

  factory TeachingAssignment.fromJson(Map<String, dynamic> json) {
    final sectionMap = (json['section'] as Map?)?.cast<String, dynamic>();
    final schoolClass =
        (sectionMap?['school_class'] as Map?)?.cast<String, dynamic>();
    final subjectMap = (json['subject'] as Map?)?.cast<String, dynamic>();
    return TeachingAssignment(
      id: (json['id'] as num).toInt(),
      subject: subjectMap?['name']?.toString(),
      subjectId:
          ((json['subject_id'] ?? subjectMap?['id']) as num?)?.toInt(),
      section: sectionMap?['name']?.toString(),
      sectionId: ((json['class_section_id'] ?? sectionMap?['id']) as num?)
          ?.toInt(),
      className: schoolClass?['name']?.toString(),
      weeklyPeriods: (json['weekly_periods'] as num?)?.toInt() ?? 0,
      isHomeroom: json['is_homeroom'] == true,
    );
  }
}

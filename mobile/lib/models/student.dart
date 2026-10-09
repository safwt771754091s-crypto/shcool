/// Student & admission models.
library;

class Student {
  Student({
    required this.id,
    required this.fullName,
    required this.studentNumber,
    this.gender,
    this.birthDate,
    this.nationalId,
    this.phone,
    this.address,
    this.status,
    this.classSectionId,
    this.sectionName,
    this.className,
  });

  final int id;
  final String fullName;
  final String studentNumber;
  final String? gender;
  final String? birthDate;
  final String? nationalId;
  final String? phone;
  final String? address;
  final String? status;
  final int? classSectionId;
  final String? sectionName;
  final String? className;

  String get statusLabel => switch (status) {
        'applicant' => 'متقدّم',
        'enrolled' => 'مُسجَّل',
        'graduated' => 'متخرّج',
        'withdrawn' => 'منسحب',
        'transferred' => 'منقول',
        _ => status ?? '—',
      };

  String get genderLabel => switch (gender) {
        'male' => 'ذكر',
        'female' => 'أنثى',
        _ => gender ?? '—',
      };

  factory Student.fromJson(Map<String, dynamic> json) {
    final section = (json['section'] as Map?)?.cast<String, dynamic>();
    final schoolClass =
        (section?['school_class'] as Map?)?.cast<String, dynamic>();
    return Student(
      id: (json['id'] as num).toInt(),
      fullName: json['full_name']?.toString() ?? '',
      studentNumber: json['student_number']?.toString() ?? '',
      gender: json['gender'] as String?,
      birthDate: json['birth_date'] as String?,
      nationalId: json['national_id'] as String?,
      phone: json['phone'] as String?,
      address: json['address'] as String?,
      status: json['status'] as String?,
      classSectionId: (json['class_section_id'] as num?)?.toInt(),
      sectionName: section?['name']?.toString(),
      className: schoolClass?['name']?.toString(),
    );
  }
}

class Admission {
  Admission({
    required this.id,
    required this.applicantName,
    this.status,
    this.score,
    this.guardianName,
    this.guardianPhone,
    this.gender,
    this.className,
    this.yearName,
  });

  final int id;
  final String applicantName;
  final String? status;
  final int? score;
  final String? guardianName;
  final String? guardianPhone;
  final String? gender;
  final String? className;
  final String? yearName;

  String get statusLabel => switch (status) {
        'submitted' => 'قيد المراجعة',
        'accepted' => 'مقبول',
        'rejected' => 'مرفوض',
        'enrolled' => 'مُسجَّل',
        _ => status ?? '—',
      };

  factory Admission.fromJson(Map<String, dynamic> json) {
    final schoolClass =
        (json['school_class'] as Map?)?.cast<String, dynamic>();
    final year = (json['academic_year'] as Map?)?.cast<String, dynamic>();
    return Admission(
      id: (json['id'] as num).toInt(),
      applicantName: json['applicant_name']?.toString() ?? '',
      status: json['status'] as String?,
      score: (json['score'] as num?)?.toInt(),
      guardianName: json['guardian_name'] as String?,
      guardianPhone: json['guardian_phone'] as String?,
      gender: json['gender'] as String?,
      className: schoolClass?['name']?.toString(),
      yearName: year?['name']?.toString(),
    );
  }
}

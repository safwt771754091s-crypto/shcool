/// Exam, result-sheet and grade models.
library;

double? _double(dynamic v) {
  if (v == null) return null;
  if (v is num) return v.toDouble();
  return double.tryParse(v.toString());
}

class Exam {
  Exam({
    required this.id,
    required this.title,
    this.subject,
    this.section,
    this.className,
    this.type,
    this.heldOn,
    this.maxMark,
    this.passMark,
    this.isPublished = false,
    this.gradesCount = 0,
  });

  final int id;
  final String title;
  final String? subject;
  final String? section;
  final String? className;
  final String? type;
  final String? heldOn;
  final double? maxMark;
  final double? passMark;
  final bool isPublished;
  final int gradesCount;

  String get typeLabel => switch (type) {
        'daily' => 'يومي',
        'monthly' => 'شهري',
        'midterm' => 'نصف الفصل',
        'final' => 'نهائي',
        'quiz' => 'قصير',
        _ => type ?? '—',
      };

  factory Exam.fromJson(Map<String, dynamic> json) {
    final subject = (json['subject'] as Map?)?.cast<String, dynamic>();
    final section = (json['section'] as Map?)?.cast<String, dynamic>();
    final schoolClass =
        (section?['school_class'] as Map?)?.cast<String, dynamic>();
    return Exam(
      id: (json['id'] as num).toInt(),
      title: json['title']?.toString() ?? '',
      subject: subject?['name']?.toString(),
      section: section?['name']?.toString(),
      className: schoolClass?['name']?.toString(),
      type: json['type'] as String?,
      heldOn: json['held_on'] as String?,
      maxMark: _double(json['max_mark']),
      passMark: _double(json['pass_mark']),
      isPublished: json['is_published'] == true,
      gradesCount: (json['grades_count'] as num?)?.toInt() ?? 0,
    );
  }
}

class SubjectResult {
  SubjectResult({
    required this.subject,
    this.exams = 0,
    this.average = 0,
    this.passed = true,
  });

  final String subject;
  final int exams;
  final double average;
  final bool passed;

  factory SubjectResult.fromJson(Map<String, dynamic> json) => SubjectResult(
        subject: json['subject']?.toString() ?? '',
        exams: (json['exams'] as num?)?.toInt() ?? 0,
        average: (json['average'] as num?)?.toDouble() ?? 0,
        passed: json['passed'] != false,
      );
}

class ResultSheet {
  ResultSheet({
    required this.studentId,
    required this.fullName,
    this.studentNumber,
    this.subjects = const [],
    this.overallAverage = 0,
    this.rank,
  });

  final int studentId;
  final String fullName;
  final String? studentNumber;
  final List<SubjectResult> subjects;
  final double overallAverage;
  final int? rank;

  factory ResultSheet.fromJson(Map<String, dynamic> json) => ResultSheet(
        studentId: (json['student_id'] as num).toInt(),
        fullName: json['full_name']?.toString() ?? '',
        studentNumber: json['student_number']?.toString(),
        subjects: (json['subjects'] as List?)
                ?.map((e) =>
                    SubjectResult.fromJson((e as Map).cast<String, dynamic>()))
                .toList() ??
            const [],
        overallAverage: (json['overall_average'] as num?)?.toDouble() ?? 0,
        rank: (json['rank'] as num?)?.toInt(),
      );
}

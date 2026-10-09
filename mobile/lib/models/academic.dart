/// Academic structure models: years, terms, subjects, classes, sections.
///
/// Decimal columns (`pass_mark`, `max_mark`) arrive as strings from Laravel's
/// `decimal:2` cast, so they are parsed through [numOrNull].
library;

num? _num(dynamic v) {
  if (v == null) return null;
  if (v is num) return v;
  return num.tryParse(v.toString());
}

double? _double(dynamic v) => _num(v)?.toDouble();

class AcademicYear {
  AcademicYear({
    required this.id,
    required this.name,
    this.startsOn,
    this.endsOn,
    this.isCurrent = false,
    this.terms = const [],
  });

  final int id;
  final String name;
  final String? startsOn;
  final String? endsOn;
  final bool isCurrent;
  final List<Term> terms;

  factory AcademicYear.fromJson(Map<String, dynamic> json) => AcademicYear(
        id: (json['id'] as num).toInt(),
        name: json['name']?.toString() ?? '',
        startsOn: json['starts_on'] as String?,
        endsOn: json['ends_on'] as String?,
        isCurrent: json['is_current'] == true,
        terms: (json['terms'] as List?)
                ?.map((e) => Term.fromJson((e as Map).cast<String, dynamic>()))
                .toList() ??
            const [],
      );
}

class Term {
  Term({
    required this.id,
    required this.name,
    this.sequence = 0,
    this.startsOn,
    this.endsOn,
    this.isCurrent = false,
  });

  final int id;
  final String name;
  final int sequence;
  final String? startsOn;
  final String? endsOn;
  final bool isCurrent;

  factory Term.fromJson(Map<String, dynamic> json) => Term(
        id: (json['id'] as num).toInt(),
        name: json['name']?.toString() ?? '',
        sequence: (json['sequence'] as num?)?.toInt() ?? 0,
        startsOn: json['starts_on'] as String?,
        endsOn: json['ends_on'] as String?,
        isCurrent: json['is_current'] == true,
      );
}

class Subject {
  Subject({
    required this.id,
    required this.name,
    this.code,
    this.stage,
    this.passMark,
    this.maxMark,
    this.isActive = true,
  });

  final int id;
  final String name;
  final String? code;
  final String? stage;
  final double? passMark;
  final double? maxMark;
  final bool isActive;

  factory Subject.fromJson(Map<String, dynamic> json) => Subject(
        id: (json['id'] as num).toInt(),
        name: json['name']?.toString() ?? '',
        code: json['code'] as String?,
        stage: json['stage'] as String?,
        passMark: _double(json['pass_mark']),
        maxMark: _double(json['max_mark']),
        isActive: json['is_active'] != false,
      );
}

class SchoolClass {
  SchoolClass({
    required this.id,
    required this.name,
    this.grade = 0,
    this.stage,
    this.branchId,
    this.sections = const [],
  });

  final int id;
  final String name;
  final int grade;
  final String? stage;
  final int? branchId;
  final List<ClassSection> sections;

  factory SchoolClass.fromJson(Map<String, dynamic> json) => SchoolClass(
        id: (json['id'] as num).toInt(),
        name: json['name']?.toString() ?? '',
        grade: (json['grade'] as num?)?.toInt() ?? 0,
        stage: json['stage'] as String?,
        branchId: (json['branch_id'] as num?)?.toInt(),
        sections: (json['sections'] as List?)
                ?.map((e) =>
                    ClassSection.fromJson((e as Map).cast<String, dynamic>()))
                .toList() ??
            const [],
      );
}

class ClassSection {
  ClassSection({
    required this.id,
    required this.name,
    this.schoolClassId,
    this.capacity = 0,
    this.room,
    this.homeroomTeacherId,
  });

  final int id;
  final String name;
  final int? schoolClassId;
  final int capacity;
  final String? room;
  final int? homeroomTeacherId;

  factory ClassSection.fromJson(Map<String, dynamic> json) => ClassSection(
        id: (json['id'] as num).toInt(),
        name: json['name']?.toString() ?? '',
        schoolClassId: (json['school_class_id'] as num?)?.toInt(),
        capacity: (json['capacity'] as num?)?.toInt() ?? 0,
        room: json['room'] as String?,
        homeroomTeacherId: (json['homeroom_teacher_id'] as num?)?.toInt(),
      );
}

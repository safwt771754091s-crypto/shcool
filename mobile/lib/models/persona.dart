import '../models/app_user.dart';

/// Maps the first role name returned by the API to a client-side persona.
enum Persona { teacher, parent, student, staff }

Persona personaFor(AppUser? user) {
  if (user == null) return Persona.staff;
  final roles = user.roles.map((r) => r.toLowerCase()).toSet();

  if (roles.contains('student')) return Persona.student;
  if (roles.contains('parent') || roles.contains('guardian')) return Persona.parent;
  if (roles.contains('teacher') ||
      roles.contains('teacher_assistant') ||
      roles.contains('assistant_teacher')) {
    return Persona.teacher;
  }
  return Persona.staff;
}

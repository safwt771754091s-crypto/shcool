import '../models/app_user.dart';

/// Maps the first role name returned by the API to a client-side persona.
enum Persona { owner, teacher, parent, student, staff }

Persona personaFor(AppUser? user) {
  if (user == null) return Persona.staff;
  final roles = user.roles.map((r) => r.toLowerCase()).toSet();

  // Platform owner / super admin / ministry-level accounts manage the whole
  // hierarchy, so they get the owner dashboard.
  if (user.isPlatformAdmin ||
      roles.contains('owner') ||
      roles.contains('super_admin') ||
      roles.contains('ministry_admin') ||
      roles.contains('governorate_admin')) {
    return Persona.owner;
  }
  if (roles.contains('student')) return Persona.student;
  if (roles.contains('parent') || roles.contains('guardian')) return Persona.parent;
  if (roles.contains('teacher') ||
      roles.contains('teacher_assistant') ||
      roles.contains('assistant_teacher')) {
    return Persona.teacher;
  }
  return Persona.staff;
}

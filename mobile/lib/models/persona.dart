import '../models/app_user.dart';

/// Maps the first role name returned by the API to a client-side persona.
enum Persona { owner, minister, manager, teacher, parent, student, staff }

/// Tenant-scoped leadership roles that land on the manager dashboard.
const _managerRoles = {
  'school_manager',
  'branch_manager',
  'vice_principal',
  'directorate_admin',
  'student_affairs',
  'accountant',
  'secretary',
};

Persona personaFor(AppUser? user) {
  if (user == null) return Persona.staff;
  final roles = user.roles.map((r) => r.toLowerCase()).toSet();

  // The Minister of Education is a read-only, platform-wide monitoring
  // account, so it is checked before the platform-admin branch.
  if (roles.contains('minister')) return Persona.minister;

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
  if (roles.intersection(_managerRoles).isNotEmpty) return Persona.manager;
  return Persona.staff;
}

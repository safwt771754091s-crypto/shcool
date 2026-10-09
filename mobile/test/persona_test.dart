import 'package:flutter_test/flutter_test.dart';
import 'package:school_app/models/app_user.dart';
import 'package:school_app/models/persona.dart';

AppUser user(List<String> roles, {bool platformAdmin = false}) => AppUser(
      id: 1,
      name: 'اسم',
      email: 'a@b.c',
      isPlatformAdmin: platformAdmin,
      roles: roles,
    );

void main() {
  test('the minister role maps to the minister persona (before owner)', () {
    expect(personaFor(user(['minister'], platformAdmin: true)), Persona.minister);
  });

  test('the owner / platform admin maps to the owner persona', () {
    expect(personaFor(user(['owner'])), Persona.owner);
    expect(personaFor(user([], platformAdmin: true)), Persona.owner);
  });

  test('school leadership roles map to the manager persona', () {
    expect(personaFor(user(['school_manager'])), Persona.manager);
    expect(personaFor(user(['vice_principal'])), Persona.manager);
    expect(personaFor(user(['accountant'])), Persona.manager);
  });

  test('teacher, parent and student keep their personas', () {
    expect(personaFor(user(['teacher'])), Persona.teacher);
    expect(personaFor(user(['parent'])), Persona.parent);
    expect(personaFor(user(['student'])), Persona.student);
    expect(personaFor(user(['something_else'])), Persona.staff);
  });
}

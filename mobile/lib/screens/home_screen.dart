import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/persona.dart';
import '../state/auth_provider.dart';
import 'parent_portal_screen.dart';
import 'staff_overview_screen.dart';
import 'student_portal_screen.dart';
import 'teacher_home_screen.dart';

class HomeScreen extends StatelessWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final user = context.select<AuthProvider, dynamic>((a) => a.user);
    final persona = personaFor(user);

    return switch (persona) {
      Persona.teacher => const TeacherHomeScreen(),
      Persona.parent => const ParentPortalScreen(),
      Persona.student => const StudentPortalScreen(),
      Persona.staff => const StaffOverviewScreen(),
    };
  }
}

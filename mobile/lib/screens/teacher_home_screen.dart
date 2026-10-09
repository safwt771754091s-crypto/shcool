import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../services/teacher_service.dart';
import '../state/auth_provider.dart';
import '../widgets/async_view.dart';
import '../widgets/stat_card.dart';

class TeacherHomeScreen extends StatefulWidget {
  const TeacherHomeScreen({super.key});

  @override
  State<TeacherHomeScreen> createState() => _TeacherHomeScreenState();
}

class _TeacherHomeScreenState extends State<TeacherHomeScreen> {
  late Future<TeacherDashboard?> _future;

  @override
  void initState() {
    super.initState();
    _future = context.read<TeacherService>().dashboard();
  }

  void _reload() {
    setState(() => _future = context.read<TeacherService>().dashboard());
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();

    return Scaffold(
      appBar: AppBar(
        title: const Text('لوحة المعلم'),
        actions: [
          IconButton(
            tooltip: 'تسجيل الخروج',
            icon: const Icon(Icons.logout),
            onPressed: () => auth.logout(),
          ),
        ],
      ),
      body: AsyncView<TeacherDashboard?>(
        future: _future,
        onRetry: _reload,
        builder: (context, dashboard) {
          if (dashboard == null) {
            return const Center(
              child: Text('لا يوجد ملف معلم مرتبط بحسابك.'),
            );
          }
          return RefreshIndicator(
            onRefresh: () async => _reload(),
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Text(dashboard.fullName,
                    style: Theme.of(context).textTheme.titleLarge),
                const SizedBox(height: 16),
                GridView.count(
                  crossAxisCount: 2,
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  mainAxisSpacing: 12,
                  crossAxisSpacing: 12,
                  childAspectRatio: 1.5,
                  children: [
                    StatCard(
                      label: 'الحصص الأسبوعية',
                      value: '${dashboard.weeklyPeriods}',
                      icon: Icons.schedule,
                    ),
                    StatCard(
                      label: 'الشعب',
                      value: '${dashboard.sections}',
                      icon: Icons.groups_outlined,
                    ),
                    StatCard(
                      label: 'المواد',
                      value: '${dashboard.subjects}',
                      icon: Icons.menu_book_outlined,
                    ),
                    StatCard(
                      label: 'جلسات الحضور',
                      value: '${dashboard.sessionsTaken}',
                      icon: Icons.fact_check_outlined,
                    ),
                    StatCard(
                      label: 'مذكرات معلّقة',
                      value: '${dashboard.pendingPreparations}',
                      icon: Icons.edit_note,
                      color: Colors.orange.shade800,
                    ),
                    StatCard(
                      label: 'اختبارات قادمة',
                      value: '${dashboard.upcomingExams.length}',
                      icon: Icons.quiz_outlined,
                    ),
                  ],
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

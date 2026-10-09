import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/portal.dart';
import '../services/portal_service.dart';
import '../state/auth_provider.dart';
import '../widgets/async_view.dart';
import '../widgets/stat_card.dart';
import 'national_ranking_screen.dart';
import 'notifications_screen.dart';

class StudentPortalScreen extends StatefulWidget {
  const StudentPortalScreen({super.key});

  @override
  State<StudentPortalScreen> createState() => _StudentPortalScreenState();
}

class _StudentPortalScreenState extends State<StudentPortalScreen> {
  late Future<ChildSummary?> _future;

  @override
  void initState() {
    super.initState();
    _future = context.read<PortalService>().studentDashboard();
  }

  void _reload() {
    setState(() => _future = context.read<PortalService>().studentDashboard());
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();

    return Scaffold(
      appBar: AppBar(
        title: const Text('بوابة الطالب'),
        actions: [
          IconButton(
            tooltip: 'تسجيل الخروج',
            icon: const Icon(Icons.logout),
            onPressed: () => auth.logout(),
          ),
        ],
      ),
      body: AsyncView<ChildSummary?>(
        future: _future,
        onRetry: _reload,
        builder: (context, data) {
          if (data == null) {
            return const Center(
              child: Text('لا يوجد ملف طالب مرتبط بحسابك.'),
            );
          }
          return RefreshIndicator(
            onRefresh: () async => _reload(),
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Text(data.fullName,
                    style: Theme.of(context).textTheme.titleLarge),
                const SizedBox(height: 4),
                Text([data.className, data.section]
                    .where((e) => e != null && e.isNotEmpty)
                    .join(' - ')),
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
                      label: 'نسبة الحضور',
                      value: '${data.attendance.rate.toStringAsFixed(1)}%',
                      icon: Icons.fact_check_outlined,
                    ),
                    StatCard(
                      label: 'المعدّل العام',
                      value: data.gradeAverage?.toStringAsFixed(1) ?? '-',
                      icon: Icons.grade_outlined,
                    ),
                    StatCard(
                      label: 'أيام الغياب',
                      value: '${data.attendance.absent}',
                      icon: Icons.event_busy_outlined,
                      color: Colors.orange.shade800,
                    ),
                    StatCard(
                      label: 'المستحق',
                      value: data.balance?.toStringAsFixed(2) ?? '-',
                      icon: Icons.receipt_long_outlined,
                      color: Colors.red.shade700,
                    ),
                  ],
                ),
                if (data.upcomingExams.isNotEmpty) ...[
                  const SizedBox(height: 24),
                  Text('الاختبارات القادمة',
                      style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: 8),
                  ...data.upcomingExams.map((e) => Card(
                        child: ListTile(
                          leading: const Icon(Icons.quiz_outlined),
                          title: Text('${e['subject'] ?? e['title'] ?? 'اختبار'}'),
                          subtitle: Text('${e['held_on'] ?? ''}'),
                        ),
                      )),
                ],
                const SizedBox(height: 24),
                Card(
                  child: ListTile(
                    leading: const Icon(Icons.emoji_events_outlined),
                    title: const Text('ترتيبي في المسابقات'),
                    trailing: const Icon(Icons.chevron_left),
                    onTap: () => Navigator.of(context).push(MaterialPageRoute(
                        builder: (_) => const NationalRankingScreen())),
                  ),
                ),
                Card(
                  child: ListTile(
                    leading: const Icon(Icons.notifications_outlined),
                    title: const Text('إشعاراتي'),
                    trailing: const Icon(Icons.chevron_left),
                    onTap: () => Navigator.of(context).push(MaterialPageRoute(
                        builder: (_) => const NotificationsScreen())),
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

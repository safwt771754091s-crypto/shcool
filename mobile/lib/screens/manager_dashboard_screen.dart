import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../state/auth_provider.dart';
import 'academic_screen.dart';
import 'ai_assistant_screen.dart';
import 'attendance_report_screen.dart';
import 'exams_screen.dart';
import 'finance_screen.dart';
import 'import_screen.dart';
import 'notifications_screen.dart';
import 'reports_screen.dart';
import 'students_screen.dart';
import 'teachers_screen.dart';
import 'users_screen.dart';

/// Landing dashboard for tenant-scoped leaders (school/branch manager, vice
/// principal, accountant, secretary, student affairs).
///
/// Surfaces the manager's identity and the staff-account administration used to
/// create colleagues and grant their roles. Deeper academic/finance management
/// runs on the web dashboard.
class ManagerDashboardScreen extends StatelessWidget {
  const ManagerDashboardScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final user = auth.user;
    final canManageUsers = user?.can('users.create') ?? false;

    return Scaffold(
      appBar: AppBar(
        title: const Text('لوحة المدير'),
        actions: [
          IconButton(
            tooltip: 'تسجيل الخروج',
            icon: const Icon(Icons.logout),
            onPressed: () => auth.logout(),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                children: [
                  CircleAvatar(
                    radius: 26,
                    child: Text(
                      (user?.name.isNotEmpty ?? false) ? user!.name.substring(0, 1) : '؟',
                      style: const TextStyle(fontSize: 20),
                    ),
                  ),
                  const SizedBox(width: 16),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(user?.name ?? '',
                            style: Theme.of(context).textTheme.titleMedium),
                        const SizedBox(height: 4),
                        Text(user?.email ?? ''),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          if (canManageUsers)
            Card(
              clipBehavior: Clip.antiAlias,
              child: ListTile(
                leading: const Icon(Icons.manage_accounts_outlined),
                title: const Text('حسابات الكوادر والصلاحيات'),
                subtitle: const Text('إنشاء مدراء ومعلمين وحسابات وإسناد الأدوار'),
                trailing: const Icon(Icons.chevron_left),
                onTap: () => Navigator.of(context).push(
                  MaterialPageRoute(builder: (_) => const UsersScreen()),
                ),
              ),
            ),
          const SizedBox(height: 16),
          Card(
            clipBehavior: Clip.antiAlias,
            color: Theme.of(context).colorScheme.primaryContainer,
            child: ListTile(
              leading: const Icon(Icons.smart_toy_outlined),
              title: const Text('وكلاء الذكاء الاصطناعي'),
              subtitle: const Text('اسأل مساعد الإدارة عن أرقام مدرستك: الطلاب، الحضور، المالية'),
              trailing: const Icon(Icons.chevron_left),
              onTap: () => Navigator.of(context).push(
                MaterialPageRoute(
                    builder: (_) => const AiAssistantScreen(initialAgent: 'manager_assistant')),
              ),
            ),
          ),
          const SizedBox(height: 16),
          Text('الوحدات الأكاديمية',
              style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 8),
          _moduleTile(
            context,
            icon: Icons.account_tree_outlined,
            title: 'الهيكل الأكاديمي',
            subtitle: 'السنوات الدراسية، المواد، الفصول والشعب',
            screen: const AcademicScreen(),
          ),
          _moduleTile(
            context,
            icon: Icons.groups_outlined,
            title: 'الطلاب والقبول',
            subtitle: 'سجل الطلاب، التسجيل، وتحديث الحالات',
            screen: const StudentsScreen(),
          ),
          _moduleTile(
            context,
            icon: Icons.school_outlined,
            title: 'المعلمون',
            subtitle: 'سجل المعلمين وتوزيعهم على المواد والشعب',
            screen: const TeachersScreen(),
          ),
          _moduleTile(
            context,
            icon: Icons.fact_check_outlined,
            title: 'الحضور والغياب',
            subtitle: 'تقارير الحضور اليومي وتنبيهات الغياب',
            screen: const AttendanceReportScreen(),
          ),
          _moduleTile(
            context,
            icon: Icons.quiz_outlined,
            title: 'الاختبارات والنتائج',
            subtitle: 'رصد الدرجات وكشوف النتائج مع الترتيب',
            screen: const ExamsScreen(),
          ),
          _moduleTile(
            context,
            icon: Icons.account_balance_wallet_outlined,
            title: 'الرسوم والمدفوعات',
            subtitle: 'الفواتير، التحصيل، والملخّص المالي',
            screen: const FinanceScreen(),
          ),
          _moduleTile(
            context,
            icon: Icons.bar_chart_outlined,
            title: 'التقارير',
            subtitle: 'تقارير الطلاب والمعلمين والحضور والفواتير',
            screen: const ReportsScreen(),
          ),
          _moduleTile(
            context,
            icon: Icons.upload_file_outlined,
            title: 'ترحيل البيانات',
            subtitle: 'استيراد الطلاب والمعلمين عبر قوالب Excel/CSV',
            screen: const ImportScreen(),
          ),
          _moduleTile(
            context,
            icon: Icons.notifications_outlined,
            title: 'الإشعارات',
            subtitle: 'صندوق الوارد وتفضيلات القنوات',
            screen: const NotificationsScreen(),
          ),
        ],
      ),
    );
  }

  Widget _moduleTile(
    BuildContext context, {
    required IconData icon,
    required String title,
    required String subtitle,
    required Widget screen,
  }) {
    return Card(
      clipBehavior: Clip.antiAlias,
      child: ListTile(
        leading: Icon(icon),
        title: Text(title),
        subtitle: Text(subtitle),
        trailing: const Icon(Icons.chevron_left),
        onTap: () => Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => screen),
        ),
      ),
    );
  }
}

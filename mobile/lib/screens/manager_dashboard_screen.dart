import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../state/auth_provider.dart';
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
          Text(
            'لإدارة الطلاب والفصول والدرجات والتقارير، استخدم لوحة التحكم على الويب.',
            style: Theme.of(context).textTheme.bodyMedium,
          ),
        ],
      ),
    );
  }
}

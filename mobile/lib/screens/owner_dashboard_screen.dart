import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/config/app_config.dart';
import '../state/auth_provider.dart';
import 'admin_hierarchy_screen.dart';
import 'mini_apps_screen.dart';
import 'monitoring_screen.dart';

/// Owner/ministry landing dashboard: quick access to the administrative
/// hierarchy and the mini-app registry, plus the signed-in identity.
class OwnerDashboardScreen extends StatelessWidget {
  const OwnerDashboardScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final user = auth.user;

    return Scaffold(
      appBar: AppBar(
        title: const Text('لوحة المالك'),
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
            color: Theme.of(context).colorScheme.primaryContainer,
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                children: [
                  CircleAvatar(
                    radius: 26,
                    backgroundColor: Theme.of(context).colorScheme.primary,
                    child: const Icon(Icons.workspace_premium_outlined,
                        color: Colors.white),
                  ),
                  const SizedBox(width: 16),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(AppConfig.platformOwnerTitle,
                            style: Theme.of(context).textTheme.labelMedium),
                        const SizedBox(height: 4),
                        Text(AppConfig.platformOwner,
                            style: Theme.of(context).textTheme.titleMedium),
                        const SizedBox(height: 4),
                        Text(user?.email ?? '',
                            style: Theme.of(context).textTheme.bodySmall),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          const _SectionTitle('إدارة الهيكل الإداري'),
          _DashboardTile(
            icon: Icons.account_tree_outlined,
            title: 'الهيكل الإداري',
            subtitle: 'الوزارة ← المحافظة ← المديرية ← المدرسة ← الفرع',
            onTap: () => Navigator.of(context).push(
              MaterialPageRoute(builder: (_) => const AdminHierarchyScreen()),
            ),
          ),
          const SizedBox(height: 8),
          _DashboardTile(
            icon: Icons.add_business_outlined,
            title: 'إضافة محافظة',
            subtitle: 'إنشاء محافظة جديدة تحت الوزارة',
            onTap: () => Navigator.of(context).push(
              MaterialPageRoute(builder: (_) => const AdminHierarchyScreen()),
            ),
          ),
          const SizedBox(height: 16),
          const _SectionTitle('التطبيقات المصغّرة'),
          _DashboardTile(
            icon: Icons.apps_outlined,
            title: 'سجل التطبيقات',
            subtitle: 'إنشاء تطبيق مصغّر ونشره على الجهات',
            onTap: () => Navigator.of(context).push(
              MaterialPageRoute(builder: (_) => const MiniAppsScreen()),
            ),
          ),
          const SizedBox(height: 16),
          const _SectionTitle('المراقبة'),
          _DashboardTile(
            icon: Icons.monitor_heart_outlined,
            title: 'المراقبة الشاملة للمنصة',
            subtitle: 'نظرة قراءة فقط على كل المحافظات والمدارس والكشوف',
            onTap: () => Navigator.of(context).push(
              MaterialPageRoute(builder: (_) => const MonitoringScreen()),
            ),
          ),
        ],
      ),
    );
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle(this.text);
  final String text;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: 8),
        child: Text(text, style: Theme.of(context).textTheme.titleSmall),
      );
}

class _DashboardTile extends StatelessWidget {
  const _DashboardTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Card(
      clipBehavior: Clip.antiAlias,
      child: ListTile(
        leading: Icon(icon),
        title: Text(title),
        subtitle: Text(subtitle),
        trailing: const Icon(Icons.chevron_left),
        onTap: onTap,
      ),
    );
  }
}

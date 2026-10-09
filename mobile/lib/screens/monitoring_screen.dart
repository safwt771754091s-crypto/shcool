import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/config/app_config.dart';
import '../models/monitoring.dart';
import '../services/admin_service.dart';
import '../widgets/async_view.dart';
import '../widgets/stat_card.dart';

/// The platform-wide monitoring view (رابط المراقبة الشامل).
///
/// Read-only roll-up of the whole country: organisation counts, student and
/// teacher totals, and a per-school breakdown. Reachable both from the
/// minister dashboard and directly at the `/monitor` URL.
class MonitoringScreen extends StatefulWidget {
  const MonitoringScreen({super.key});

  @override
  State<MonitoringScreen> createState() => _MonitoringScreenState();
}

class _MonitoringScreenState extends State<MonitoringScreen> {
  late Future<MonitoringOverview> _future;

  @override
  void initState() {
    super.initState();
    _future = context.read<AdminService>().monitoringOverview();
  }

  void _reload() {
    setState(() => _future = context.read<AdminService>().monitoringOverview());
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('المراقبة الشاملة'),
        actions: [
          IconButton(
            tooltip: 'تحديث',
            icon: const Icon(Icons.refresh),
            onPressed: _reload,
          ),
        ],
      ),
      body: AsyncView<MonitoringOverview>(
        future: _future,
        onRetry: _reload,
        builder: (context, data) {
          final t = data.totals;
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Card(
                color: Theme.of(context).colorScheme.primaryContainer,
                child: const Padding(
                  padding: EdgeInsets.all(16),
                  child: Row(
                    children: [
                      Icon(Icons.visibility_outlined),
                      SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          'عرض للقراءة فقط — يغطي كامل المنصة (جميع المحافظات والمدارس).',
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 16),
              GridView.count(
                crossAxisCount: 2,
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                mainAxisSpacing: 12,
                crossAxisSpacing: 12,
                childAspectRatio: 1.6,
                children: [
                  StatCard(label: 'المحافظات', value: '${t['governorates'] ?? 0}', icon: Icons.map_outlined),
                  StatCard(label: 'المديريات', value: '${t['directorates'] ?? 0}', icon: Icons.apartment_outlined),
                  StatCard(label: 'المدارس', value: '${t['schools'] ?? 0}', icon: Icons.school_outlined),
                  StatCard(label: 'الفروع', value: '${t['branches'] ?? 0}', icon: Icons.storefront_outlined),
                  StatCard(label: 'الطلاب', value: '${t['students'] ?? 0}', icon: Icons.groups_outlined),
                  StatCard(label: 'المعلمون', value: '${t['teachers'] ?? 0}', icon: Icons.badge_outlined),
                  StatCard(label: 'حسابات الكوادر', value: '${t['staff_accounts'] ?? 0}', icon: Icons.manage_accounts_outlined),
                  StatCard(label: 'سجلات الحضور', value: '${t['attendance_records'] ?? 0}', icon: Icons.event_available_outlined),
                ],
              ),
              const SizedBox(height: 24),
              Text('المدارس', style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 8),
              if (data.perSchool.isEmpty)
                const Padding(
                  padding: EdgeInsets.all(8),
                  child: Text('لا توجد مدارس بعد.'),
                )
              else
                ...data.perSchool.map((s) => Card(
                      child: ListTile(
                        leading: const Icon(Icons.school_outlined),
                        title: Text(s.name),
                        subtitle: Text(s.code),
                        trailing: Text('${s.students} طالب • ${s.teachers} معلم',
                            style: Theme.of(context).textTheme.bodySmall),
                      ),
                    )),
              if (data.generatedAt != null) ...[
                const SizedBox(height: 16),
                Text('آخر تحديث: ${data.generatedAt}',
                    style: Theme.of(context).textTheme.bodySmall),
              ],
              const SizedBox(height: 24),
              Text(
                '${AppConfig.platformOwnerTitle}: ${AppConfig.platformOwner}',
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ],
          );
        },
      ),
    );
  }
}

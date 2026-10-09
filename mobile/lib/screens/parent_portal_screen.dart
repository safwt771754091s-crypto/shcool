import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/portal.dart';
import '../services/portal_service.dart';
import '../services/sync_service.dart';
import '../state/auth_provider.dart';
import '../widgets/async_view.dart';
import '../widgets/stat_card.dart';

class ParentPortalScreen extends StatefulWidget {
  const ParentPortalScreen({super.key});

  @override
  State<ParentPortalScreen> createState() => _ParentPortalScreenState();
}

class _ParentPortalScreenState extends State<ParentPortalScreen> {
  late Future<List<ChildSummary>> _future;

  @override
  void initState() {
    super.initState();
    _future = context.read<PortalService>().parentChildren();
  }

  void _reload() {
    setState(() => _future = context.read<PortalService>().parentChildren());
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();

    return Scaffold(
      appBar: AppBar(
        title: const Text('بوابة ولي الأمر'),
        actions: [
          IconButton(
            tooltip: 'تسجيل الخروج',
            icon: const Icon(Icons.logout),
            onPressed: () => auth.logout(),
          ),
        ],
      ),
      body: AsyncView<List<ChildSummary>>(
        future: _future,
        onRetry: _reload,
        builder: (context, children) {
          if (children.isEmpty) {
            return const Center(child: Text('لا يوجد أبناء مرتبطون بحسابك.'));
          }
          return RefreshIndicator(
            onRefresh: () async => _reload(),
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                const SyncBanner(),
                ...children.map((c) => _ChildCard(child: c)),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _ChildCard extends StatelessWidget {
  const _ChildCard({required this.child});

  final ChildSummary child;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 16),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(child.fullName,
                style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 4),
            Text([child.className, child.section]
                .where((e) => e != null && e.isNotEmpty)
                .join(' - ')),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: StatCard(
                    label: 'نسبة الحضور',
                    value: '${child.attendance.rate.toStringAsFixed(1)}%',
                    icon: Icons.fact_check_outlined,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: StatCard(
                    label: 'المعدّل العام',
                    value: child.gradeAverage == null
                        ? '-'
                        : child.gradeAverage!.toStringAsFixed(1),
                    icon: Icons.grade_outlined,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: StatCard(
                    label: 'المستحق',
                    value: child.balance == null
                        ? '-'
                        : child.balance!.toStringAsFixed(2),
                    icon: Icons.receipt_long_outlined,
                    color: Colors.red.shade700,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: StatCard(
                    label: 'الغياب هذا الشهر',
                    value: '${child.attendance.absent}',
                    icon: Icons.event_busy_outlined,
                    color: Colors.orange.shade800,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

/// Shows how many mutations are waiting to sync (العمل دون إنترنت).
class SyncBanner extends StatefulWidget {
  const SyncBanner({super.key});

  @override
  State<SyncBanner> createState() => _SyncBannerState();
}

class _SyncBannerState extends State<SyncBanner> {
  int _pending = 0;

  @override
  void initState() {
    super.initState();
    _refresh();
  }

  Future<void> _refresh() async {
    final count = await context.read<SyncService>().pendingCount();
    if (mounted) setState(() => _pending = count);
  }

  Future<void> _flush() async {
    try {
      final synced = await context.read<SyncService>().flush();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('تمت مزامنة $synced عنصرًا.')),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text('فشلت المزامنة: $e')));
    }
    await _refresh();
  }

  @override
  Widget build(BuildContext context) {
    if (_pending == 0) return const SizedBox.shrink();
    return Card(
      color: Colors.amber.shade50,
      margin: const EdgeInsets.only(bottom: 16),
      child: ListTile(
        leading: const Icon(Icons.cloud_upload_outlined),
        title: Text('$_pending تغييرات بانتظار المزامنة'),
        trailing: TextButton(onPressed: _flush, child: const Text('مزامنة الآن')),
      ),
    );
  }
}

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/organization.dart';
import '../services/admin_service.dart';
import '../widgets/async_view.dart';

/// Mini-app registry: create app templates and publish them to an
/// organization (school / directorate / governorate / ministry).
class MiniAppsScreen extends StatefulWidget {
  const MiniAppsScreen({super.key});

  @override
  State<MiniAppsScreen> createState() => _MiniAppsScreenState();
}

class _MiniAppsScreenState extends State<MiniAppsScreen> {
  late Future<List<MiniApp>> _future;

  @override
  void initState() {
    super.initState();
    _future = context.read<AdminService>().apps();
  }

  void _reload() {
    setState(() => _future = context.read<AdminService>().apps());
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('سجل التطبيقات المصغّرة')),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _create,
        icon: const Icon(Icons.add),
        label: const Text('تطبيق جديد'),
      ),
      body: AsyncView<List<MiniApp>>(
        future: _future,
        onRetry: _reload,
        builder: (context, apps) {
          if (apps.isEmpty) {
            return const Center(child: Text('لا توجد تطبيقات بعد.'));
          }
          return ListView.builder(
            padding: const EdgeInsets.only(bottom: 96),
            itemCount: apps.length,
            itemBuilder: (context, i) {
              final app = apps[i];
              return Card(
                child: ListTile(
                  leading: const Icon(Icons.widgets_outlined),
                  title: Text(app.name),
                  subtitle: Text(
                    [
                      if (app.category != null) app.category!,
                      'منشور على ${app.instancesCount} جهة',
                    ].join(' • '),
                  ),
                  trailing: IconButton(
                    tooltip: 'نشر على جهة',
                    icon: const Icon(Icons.send_outlined),
                    onPressed: () => _publish(app),
                  ),
                ),
              );
            },
          );
        },
      ),
    );
  }

  Future<void> _create() async {
    final name = TextEditingController();
    final slug = TextEditingController();
    final category = TextEditingController();

    final ok = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('تطبيق مصغّر جديد'),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: name,
                decoration: const InputDecoration(labelText: 'الاسم'),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: slug,
                decoration: const InputDecoration(
                    labelText: 'المعرّف (بالإنجليزية، فريد)'),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: category,
                decoration:
                    const InputDecoration(labelText: 'التصنيف (اختياري)'),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('إلغاء'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('إنشاء'),
          ),
        ],
      ),
    );

    if (ok != true || !mounted) return;
    if (name.text.trim().isEmpty || slug.text.trim().isEmpty) return;
    try {
      await context.read<AdminService>().createApp(
            name: name.text.trim(),
            slug: slug.text.trim(),
            category: category.text.trim(),
          );
      _reload();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('تم إنشاء التطبيق.')),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text('فشل الإنشاء: $e')));
      }
    }
  }

  Future<void> _publish(MiniApp app) async {
    final service = context.read<AdminService>();
    final tree = await service.tree();
    if (!mounted) return;

    final all = <Organization>[];
    void walk(List<Organization> nodes) {
      for (final n in nodes) {
        all.add(n);
        walk(n.children);
      }
    }

    walk(tree);

    final chosen = await showDialog<Organization>(
      context: context,
      builder: (_) => AlertDialog(
        title: Text('نشر «${app.name}»'),
        content: SizedBox(
          width: 360,
          height: 400,
          child: ListView(
            children: all
                .map((o) => ListTile(
                      leading: Text(o.level.toString()),
                      title: Text(o.name),
                      subtitle: Text(o.typeLabel),
                      onTap: () => Navigator.of(context).pop(o),
                    ))
                .toList(),
          ),
        ),
      ),
    );

    if (chosen == null || !mounted) return;
    try {
      await service.publishApp(appId: app.id, organizationId: chosen.id);
      _reload();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('تم النشر على ${chosen.name}.')),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text('فشل النشر: $e')));
      }
    }
  }
}

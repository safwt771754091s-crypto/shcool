import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/notification.dart';
import '../services/notification_service.dart';
import '../widgets/async_view.dart';

/// The signed-in user's notification inbox and channel preferences.
class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  late Future<List<NotificationItem>> _inbox;

  @override
  void initState() {
    super.initState();
    _inbox = context.read<NotificationService>().inbox();
  }

  void _reload() =>
      setState(() => _inbox = context.read<NotificationService>().inbox());

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('الإشعارات'),
          actions: [
            IconButton(onPressed: _reload, icon: const Icon(Icons.refresh)),
          ],
          bottom: const TabBar(tabs: [
            Tab(text: 'الوارد'),
            Tab(text: 'التفضيلات'),
          ]),
        ),
        body: TabBarView(
          children: [
            AsyncView<List<NotificationItem>>(
              future: _inbox,
              onRetry: _reload,
              builder: (context, items) => items.isEmpty
                  ? const Center(child: Text('لا توجد إشعارات.'))
                  : ListView.builder(
                      itemCount: items.length,
                      itemBuilder: (context, i) {
                        final n = items[i];
                        return Card(
                          child: ListTile(
                            leading: CircleAvatar(
                              backgroundColor: n.isUnread
                                  ? Theme.of(context).colorScheme.primaryContainer
                                  : null,
                              child: Icon(n.isUnread
                                  ? Icons.mark_email_unread_outlined
                                  : Icons.drafts_outlined),
                            ),
                            title: Text(n.title ?? 'إشعار'),
                            subtitle: Text(n.body ?? ''),
                            trailing: n.isUnread
                                ? IconButton(
                                    tooltip: 'تعليم كمقروء',
                                    icon: const Icon(Icons.done),
                                    onPressed: () async {
                                      await context
                                          .read<NotificationService>()
                                          .markRead(n.id);
                                      _reload();
                                    },
                                  )
                                : null,
                          ),
                        );
                      },
                    ),
            ),
            const _PreferencesTab(),
          ],
        ),
      ),
    );
  }
}

class _PreferencesTab extends StatefulWidget {
  const _PreferencesTab();
  @override
  State<_PreferencesTab> createState() => _PreferencesTabState();
}

class _PreferencesTabState extends State<_PreferencesTab> {
  late Future<List<NotificationPreference>> _future;
  List<NotificationPreference>? _edited;

  @override
  void initState() {
    super.initState();
    _future = context.read<NotificationService>().preferences();
  }

  Future<void> _save() async {
    final edited = _edited;
    if (edited == null) return;
    await context.read<NotificationService>().savePreferences(edited);
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('تم حفظ التفضيلات.')),
    );
  }

  @override
  Widget build(BuildContext context) {
    return AsyncView<List<NotificationPreference>>(
      future: _future,
      onRetry: () => setState(
          () => _future = context.read<NotificationService>().preferences()),
      builder: (context, prefs) {
        final list = _edited ??= List.of(prefs);
        return ListView(
          children: [
            for (var i = 0; i < list.length; i++)
              SwitchListTile(
                title: Text(list[i].label),
                value: list[i].isEnabled,
                onChanged: (v) => setState(() {
                  list[i] = NotificationPreference(
                      channel: list[i].channel, isEnabled: v);
                }),
              ),
            Padding(
              padding: const EdgeInsets.all(16),
              child: FilledButton.icon(
                onPressed: _save,
                icon: const Icon(Icons.save_outlined),
                label: const Text('حفظ التفضيلات'),
              ),
            ),
          ],
        );
      },
    );
  }
}

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../state/auth_provider.dart';

/// Landing view for administrative roles (مدير مدرسة، وزارة، حسابات...).
///
/// Deep management screens live on the web dashboard; the mobile app surfaces
/// the signed-in identity, granted roles and a shortcut to sign out.
class StaffOverviewScreen extends StatelessWidget {
  const StaffOverviewScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final user = auth.user;

    return Scaffold(
      appBar: AppBar(
        title: const Text('لوحة التحكم'),
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
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      CircleAvatar(
                        radius: 28,
                        child: Text(
                          (user?.name.isNotEmpty ?? false)
                              ? user!.name.substring(0, 1)
                              : '؟',
                          style: const TextStyle(fontSize: 22),
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
                  const SizedBox(height: 16),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: (user?.roles ?? const [])
                        .map((r) => Chip(label: Text(r)))
                        .toList(),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          const Text(
            'لإدارة الطلاب والمعلمين والفصول والتقارير، استخدم لوحة التحكم على الويب. '
            'هذا التطبيق يوفّر لوحة المعلم، بوابة ولي الأمر، بوابة الطالب، والعمل دون إنترنت.',
          ),
        ],
      ),
    );
  }
}

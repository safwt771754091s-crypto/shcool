import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/monitoring.dart';
import '../services/admin_service.dart';
import '../widgets/async_view.dart';

/// Roles a manager may grant from the dashboard. Mirrors the backend
/// allow-list (tenant-scoped roles only).
const _assignableRoles = <String, String>{
  'school_manager': 'مدير المدرسة',
  'branch_manager': 'مدير الفرع',
  'vice_principal': 'وكيل المدرسة',
  'teacher': 'معلم',
  'teacher_assistant': 'معلم مساعد',
  'student_affairs': 'شؤون الطلاب',
  'accountant': 'محاسب',
  'secretary': 'سكرتير',
  'parent': 'ولي أمر',
  'student': 'طالب',
};

/// Manage the staff accounts of the current school (create + grant roles).
class UsersScreen extends StatefulWidget {
  const UsersScreen({super.key});

  @override
  State<UsersScreen> createState() => _UsersScreenState();
}

class _UsersScreenState extends State<UsersScreen> {
  late Future<List<StaffUser>> _future;

  @override
  void initState() {
    super.initState();
    _future = context.read<AdminService>().users();
  }

  void _reload() {
    setState(() => _future = context.read<AdminService>().users());
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('حسابات الكوادر')),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _showCreateDialog,
        icon: const Icon(Icons.person_add_alt),
        label: const Text('حساب جديد'),
      ),
      body: AsyncView<List<StaffUser>>(
        future: _future,
        onRetry: _reload,
        builder: (context, users) {
          if (users.isEmpty) {
            return const Center(child: Text('لا توجد حسابات بعد.'));
          }
          return ListView.builder(
            padding: const EdgeInsets.only(bottom: 96),
            itemCount: users.length,
            itemBuilder: (context, i) {
              final u = users[i];
              return Card(
                child: ListTile(
                  leading: CircleAvatar(
                    child: Text(u.name.isNotEmpty ? u.name.substring(0, 1) : '؟'),
                  ),
                  title: Text(u.name),
                  subtitle: Text(
                    '${u.email}${u.roles.isEmpty ? '' : ' • ${u.roles.map(_roleLabel).join(', ')}'}',
                  ),
                ),
              );
            },
          );
        },
      ),
    );
  }

  String _roleLabel(String role) => _assignableRoles[role] ?? role;

  Future<void> _showCreateDialog() async {
    await showDialog<void>(
      context: context,
      builder: (_) => _CreateUserDialog(
        service: context.read<AdminService>(),
        onCreated: _reload,
      ),
    );
  }
}

class _CreateUserDialog extends StatefulWidget {
  const _CreateUserDialog({required this.service, required this.onCreated});

  final AdminService service;
  final VoidCallback onCreated;

  @override
  State<_CreateUserDialog> createState() => _CreateUserDialogState();
}

class _CreateUserDialogState extends State<_CreateUserDialog> {
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _password = TextEditingController();
  final _phone = TextEditingController();
  String _role = 'teacher';
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _password.dispose();
    _phone.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('حساب كادر جديد'),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            TextField(
              controller: _name,
              decoration: const InputDecoration(labelText: 'الاسم'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _email,
              keyboardType: TextInputType.emailAddress,
              decoration: const InputDecoration(labelText: 'البريد الإلكتروني'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _password,
              obscureText: true,
              decoration: const InputDecoration(labelText: 'كلمة المرور (8 أحرف على الأقل)'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _phone,
              keyboardType: TextInputType.phone,
              decoration: const InputDecoration(labelText: 'الهاتف (اختياري)'),
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _role,
              decoration: const InputDecoration(labelText: 'الدور'),
              items: _assignableRoles.entries
                  .map((e) => DropdownMenuItem(value: e.key, child: Text(e.value)))
                  .toList(),
              onChanged: (v) => setState(() => _role = v ?? _role),
            ),
            if (_error != null) ...[
              const SizedBox(height: 12),
              Text(_error!, style: const TextStyle(color: Colors.red)),
            ],
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: _busy ? null : () => Navigator.of(context).pop(),
          child: const Text('إلغاء'),
        ),
        FilledButton(
          onPressed: _busy ? null : _submit,
          child: _busy
              ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
              : const Text('إنشاء'),
        ),
      ],
    );
  }

  Future<void> _submit() async {
    if (_name.text.trim().isEmpty || _email.text.trim().isEmpty || _password.text.length < 8) {
      setState(() => _error = 'أدخل الاسم والبريد وكلمة مرور من 8 أحرف على الأقل.');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await widget.service.createUser(
        name: _name.text.trim(),
        email: _email.text.trim(),
        password: _password.text,
        role: _role,
        phone: _phone.text.trim(),
      );
      if (!mounted) return;
      Navigator.of(context).pop();
      widget.onCreated();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('تم إنشاء الحساب.')),
      );
    } catch (e) {
      if (mounted) {
        setState(() {
          _busy = false;
          _error = '$e';
        });
      }
    }
  }
}

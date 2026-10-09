import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/teacher.dart';
import '../services/staff_service.dart';
import '../widgets/async_view.dart';

/// Teacher roster with search and quick add.
class TeachersScreen extends StatefulWidget {
  const TeachersScreen({super.key});

  @override
  State<TeachersScreen> createState() => _TeachersScreenState();
}

class _TeachersScreenState extends State<TeachersScreen> {
  final _search = TextEditingController();
  late Future<List<Teacher>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<List<Teacher>> _load() => context.read<StaffService>().teachers(
        search: _search.text.trim().isEmpty ? null : _search.text.trim(),
      );

  void _reload() => setState(() => _future = _load());

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('المعلمون')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(12),
            child: TextField(
              controller: _search,
              textInputAction: TextInputAction.search,
              onSubmitted: (_) => _reload(),
              decoration: InputDecoration(
                hintText: 'بحث بالاسم أو الرقم الوظيفي',
                prefixIcon: const Icon(Icons.search),
                border: const OutlineInputBorder(),
                suffixIcon: IconButton(
                    icon: const Icon(Icons.refresh), onPressed: _reload),
              ),
            ),
          ),
          Expanded(
            child: AsyncView<List<Teacher>>(
              future: _future,
              onRetry: _reload,
              builder: (context, teachers) => teachers.isEmpty
                  ? const Center(child: Text('لا يوجد معلمون مطابقون.'))
                  : RefreshIndicator(
                      onRefresh: () async => _reload(),
                      child: ListView.builder(
                        itemCount: teachers.length,
                        itemBuilder: (context, i) {
                          final t = teachers[i];
                          return Card(
                            child: ListTile(
                              leading: const CircleAvatar(
                                  child: Icon(Icons.school_outlined)),
                              title: Text(t.fullName),
                              subtitle: Text([
                                t.employeeNumber,
                                if (t.specialization != null) t.specialization!,
                              ].join('  •  ')),
                              trailing: Chip(label: Text(t.statusLabel)),
                            ),
                          );
                        },
                      ),
                    ),
            ),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () async {
          final ok = await showDialog<bool>(
              context: context, builder: (_) => const _TeacherDialog());
          if (ok == true) _reload();
        },
        icon: const Icon(Icons.person_add_alt),
        label: const Text('معلم جديد'),
      ),
    );
  }
}

class _TeacherDialog extends StatefulWidget {
  const _TeacherDialog();
  @override
  State<_TeacherDialog> createState() => _TeacherDialogState();
}

class _TeacherDialogState extends State<_TeacherDialog> {
  final _name = TextEditingController();
  final _number = TextEditingController();
  final _specialization = TextEditingController();
  final _phone = TextEditingController();
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _name.dispose();
    _number.dispose();
    _specialization.dispose();
    _phone.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_name.text.trim().isEmpty || _number.text.trim().isEmpty) {
      setState(() => _error = 'الاسم والرقم الوظيفي مطلوبان.');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await context.read<StaffService>().createTeacher(
            fullName: _name.text.trim(),
            employeeNumber: _number.text.trim(),
            specialization: _specialization.text.trim().isEmpty
                ? null
                : _specialization.text.trim(),
            phone: _phone.text.trim().isEmpty ? null : _phone.text.trim(),
          );
      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      setState(() {
        _busy = false;
        _error = '$e';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('إضافة معلم'),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
                controller: _name,
                decoration: const InputDecoration(labelText: 'الاسم الكامل')),
            TextField(
                controller: _number,
                decoration:
                    const InputDecoration(labelText: 'الرقم الوظيفي')),
            TextField(
                controller: _specialization,
                decoration:
                    const InputDecoration(labelText: 'التخصص (اختياري)')),
            TextField(
                controller: _phone,
                keyboardType: TextInputType.phone,
                decoration: const InputDecoration(labelText: 'الهاتف (اختياري)')),
            if (_error != null)
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Text(_error!,
                    style:
                        TextStyle(color: Theme.of(context).colorScheme.error)),
              ),
          ],
        ),
      ),
      actions: [
        TextButton(
            onPressed: _busy ? null : () => Navigator.pop(context, false),
            child: const Text('إلغاء')),
        FilledButton(
            onPressed: _busy ? null : _submit,
            child: _busy
                ? const SizedBox(
                    width: 18, height: 18, child: CircularProgressIndicator())
                : const Text('حفظ')),
      ],
    );
  }
}

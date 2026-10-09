import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/student.dart';
import '../services/student_service.dart';
import '../widgets/async_view.dart';

/// Student roster with search, status filter, and quick enrolment.
class StudentsScreen extends StatefulWidget {
  const StudentsScreen({super.key});

  @override
  State<StudentsScreen> createState() => _StudentsScreenState();
}

class _StudentsScreenState extends State<StudentsScreen> {
  final _search = TextEditingController();
  late Future<List<Student>> _future;
  String? _status;

  static const _statuses = <String, String>{
    'enrolled': 'مُسجَّل',
    'applicant': 'متقدّم',
    'graduated': 'متخرّج',
    'withdrawn': 'منسحب',
    'transferred': 'منقول',
  };

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

  Future<List<Student>> _load() => context.read<StudentService>().students(
        status: _status,
        search: _search.text.trim().isEmpty ? null : _search.text.trim(),
      );

  void _reload() => setState(() => _future = _load());

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('الطلاب')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 12, 12, 0),
            child: Column(
              children: [
                TextField(
                  controller: _search,
                  textInputAction: TextInputAction.search,
                  onSubmitted: (_) => _reload(),
                  decoration: InputDecoration(
                    hintText: 'بحث بالاسم أو الرقم الأكاديمي',
                    prefixIcon: const Icon(Icons.search),
                    border: const OutlineInputBorder(),
                    suffixIcon: IconButton(
                        icon: const Icon(Icons.refresh), onPressed: _reload),
                  ),
                ),
                const SizedBox(height: 8),
                DropdownButtonFormField<String?>(
                  initialValue: _status,
                  decoration: const InputDecoration(
                      labelText: 'الحالة', border: OutlineInputBorder()),
                  items: [
                    const DropdownMenuItem(value: null, child: Text('الكل')),
                    for (final e in _statuses.entries)
                      DropdownMenuItem(value: e.key, child: Text(e.value)),
                  ],
                  onChanged: (v) {
                    setState(() {
                      _status = v;
                      _future = _load();
                    });
                  },
                ),
              ],
            ),
          ),
          Expanded(
            child: AsyncView<List<Student>>(
              future: _future,
              onRetry: _reload,
              builder: (context, students) => students.isEmpty
                  ? const Center(child: Text('لا يوجد طلاب مطابقون.'))
                  : RefreshIndicator(
                      onRefresh: () async => _reload(),
                      child: ListView.builder(
                        itemCount: students.length,
                        itemBuilder: (context, i) {
                          final s = students[i];
                          return Card(
                            child: ListTile(
                              leading: CircleAvatar(
                                child: Text(s.fullName.isEmpty
                                    ? '؟'
                                    : s.fullName.characters.first),
                              ),
                              title: Text(s.fullName),
                              subtitle: Text([
                                s.studentNumber,
                                if (s.className != null)
                                  '${s.className} ${s.sectionName ?? ''}',
                              ].join('  •  ')),
                              trailing: Chip(label: Text(s.statusLabel)),
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
              context: context, builder: (_) => const _StudentDialog());
          if (ok == true) _reload();
        },
        icon: const Icon(Icons.person_add_alt),
        label: const Text('طالب جديد'),
      ),
    );
  }
}

class _StudentDialog extends StatefulWidget {
  const _StudentDialog();
  @override
  State<_StudentDialog> createState() => _StudentDialogState();
}

class _StudentDialogState extends State<_StudentDialog> {
  final _name = TextEditingController();
  final _number = TextEditingController();
  final _phone = TextEditingController();
  String? _gender;
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _name.dispose();
    _number.dispose();
    _phone.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_name.text.trim().isEmpty || _number.text.trim().isEmpty) {
      setState(() => _error = 'الاسم والرقم الأكاديمي مطلوبان.');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await context.read<StudentService>().createStudent(
            fullName: _name.text.trim(),
            studentNumber: _number.text.trim(),
            gender: _gender,
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
      title: const Text('تسجيل طالب جديد'),
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
                    const InputDecoration(labelText: 'الرقم الأكاديمي')),
            DropdownButtonFormField<String>(
              initialValue: _gender,
              decoration: const InputDecoration(labelText: 'الجنس'),
              items: const [
                DropdownMenuItem(value: 'male', child: Text('ذكر')),
                DropdownMenuItem(value: 'female', child: Text('أنثى')),
              ],
              onChanged: (v) => setState(() => _gender = v),
            ),
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

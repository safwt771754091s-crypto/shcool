import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/academic.dart';
import '../services/academic_service.dart';
import '../widgets/async_view.dart';

/// Academic structure: academic years, subjects and classes (with sections).
class AcademicScreen extends StatefulWidget {
  const AcademicScreen({super.key});

  @override
  State<AcademicScreen> createState() => _AcademicScreenState();
}

class _AcademicScreenState extends State<AcademicScreen>
    with SingleTickerProviderStateMixin {
  late final TabController _tabs = TabController(length: 3, vsync: this);

  @override
  void dispose() {
    _tabs.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('الهيكل الأكاديمي'),
        bottom: TabBar(
          controller: _tabs,
          tabs: const [
            Tab(text: 'السنوات'),
            Tab(text: 'المواد'),
            Tab(text: 'الفصول'),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabs,
        children: const [_YearsTab(), _SubjectsTab(), _ClassesTab()],
      ),
    );
  }
}

class _YearsTab extends StatefulWidget {
  const _YearsTab();
  @override
  State<_YearsTab> createState() => _YearsTabState();
}

class _YearsTabState extends State<_YearsTab> {
  late Future<List<AcademicYear>> _future;

  @override
  void initState() {
    super.initState();
    _future = context.read<AcademicService>().years();
  }

  void _reload() =>
      setState(() => _future = context.read<AcademicService>().years());

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: AsyncView<List<AcademicYear>>(
        future: _future,
        onRetry: _reload,
        builder: (context, years) => years.isEmpty
            ? const Center(child: Text('لا توجد سنوات دراسية بعد.'))
            : ListView.builder(
                itemCount: years.length,
                itemBuilder: (context, i) {
                  final y = years[i];
                  return Card(
                    child: ExpansionTile(
                      title: Text(y.name),
                      subtitle: Text('${y.startsOn ?? ''} ← ${y.endsOn ?? ''}'),
                      trailing: y.isCurrent
                          ? const Chip(label: Text('الحالية'))
                          : null,
                      children: [
                        for (final t in y.terms)
                          ListTile(
                            dense: true,
                            leading: const Icon(Icons.event_note_outlined),
                            title: Text(t.name),
                            subtitle:
                                Text('${t.startsOn ?? ''} ← ${t.endsOn ?? ''}'),
                          ),
                        if (y.terms.isEmpty)
                          const ListTile(
                              dense: true, title: Text('لا توجد فصول دراسية.')),
                      ],
                    ),
                  );
                },
              ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _showYearDialog(),
        icon: const Icon(Icons.add),
        label: const Text('سنة دراسية'),
      ),
    );
  }

  Future<void> _showYearDialog() async {
    final created = await showDialog<bool>(
      context: context,
      builder: (_) => const _YearDialog(),
    );
    if (created == true) _reload();
  }
}

class _SubjectsTab extends StatefulWidget {
  const _SubjectsTab();
  @override
  State<_SubjectsTab> createState() => _SubjectsTabState();
}

class _SubjectsTabState extends State<_SubjectsTab> {
  late Future<List<Subject>> _future;

  @override
  void initState() {
    super.initState();
    _future = context.read<AcademicService>().subjects();
  }

  void _reload() =>
      setState(() => _future = context.read<AcademicService>().subjects());

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: AsyncView<List<Subject>>(
        future: _future,
        onRetry: _reload,
        builder: (context, subjects) => subjects.isEmpty
            ? const Center(child: Text('لا توجد مواد بعد.'))
            : ListView.builder(
                itemCount: subjects.length,
                itemBuilder: (context, i) {
                  final s = subjects[i];
                  return Card(
                    child: ListTile(
                      leading: const CircleAvatar(
                          child: Icon(Icons.menu_book_outlined)),
                      title: Text(s.name),
                      subtitle: Text([
                        if (s.code != null) 'الرمز: ${s.code}',
                        if (s.maxMark != null) 'من ${s.maxMark}',
                      ].join('  •  ')),
                    ),
                  );
                },
              ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () async {
          final ok = await showDialog<bool>(
              context: context, builder: (_) => const _SubjectDialog());
          if (ok == true) _reload();
        },
        icon: const Icon(Icons.add),
        label: const Text('مادة'),
      ),
    );
  }
}

class _ClassesTab extends StatefulWidget {
  const _ClassesTab();
  @override
  State<_ClassesTab> createState() => _ClassesTabState();
}

class _ClassesTabState extends State<_ClassesTab> {
  late Future<List<SchoolClass>> _future;

  @override
  void initState() {
    super.initState();
    _future = context.read<AcademicService>().classes();
  }

  void _reload() =>
      setState(() => _future = context.read<AcademicService>().classes());

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: AsyncView<List<SchoolClass>>(
        future: _future,
        onRetry: _reload,
        builder: (context, classes) => classes.isEmpty
            ? const Center(child: Text('لا توجد فصول بعد.'))
            : ListView.builder(
                itemCount: classes.length,
                itemBuilder: (context, i) {
                  final c = classes[i];
                  return Card(
                    child: ExpansionTile(
                      title: Text(c.name),
                      subtitle: Text('الصف ${c.grade}'),
                      trailing: Text('${c.sections.length} شعبة'),
                      children: [
                        for (final s in c.sections)
                          ListTile(
                            dense: true,
                            leading: const Icon(Icons.groups_outlined),
                            title: Text('شعبة ${s.name}'),
                            subtitle: s.room == null ? null : Text('قاعة ${s.room}'),
                          ),
                        if (c.sections.isEmpty)
                          const ListTile(
                              dense: true, title: Text('لا توجد شعب.')),
                      ],
                    ),
                  );
                },
              ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () async {
          final ok = await showDialog<bool>(
              context: context, builder: (_) => const _ClassDialog());
          if (ok == true) _reload();
        },
        icon: const Icon(Icons.add),
        label: const Text('فصل'),
      ),
    );
  }
}

class _YearDialog extends StatefulWidget {
  const _YearDialog();
  @override
  State<_YearDialog> createState() => _YearDialogState();
}

class _YearDialogState extends State<_YearDialog> {
  final _name = TextEditingController();
  final _starts = TextEditingController();
  final _ends = TextEditingController();
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _name.dispose();
    _starts.dispose();
    _ends.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_name.text.trim().isEmpty ||
        _starts.text.trim().isEmpty ||
        _ends.text.trim().isEmpty) {
      setState(() => _error = 'أكمل جميع الحقول.');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await context.read<AcademicService>().createYear(
            name: _name.text.trim(),
            startsOn: _starts.text.trim(),
            endsOn: _ends.text.trim(),
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
      title: const Text('سنة دراسية جديدة'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          TextField(
            controller: _name,
            decoration: const InputDecoration(labelText: 'الاسم'),
          ),
          TextField(
            controller: _starts,
            decoration:
                const InputDecoration(labelText: 'تبدأ في (YYYY-MM-DD)'),
          ),
          TextField(
            controller: _ends,
            decoration: const InputDecoration(labelText: 'تنتهي في (YYYY-MM-DD)'),
          ),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Text(_error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error)),
            ),
        ],
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

class _SubjectDialog extends StatefulWidget {
  const _SubjectDialog();
  @override
  State<_SubjectDialog> createState() => _SubjectDialogState();
}

class _SubjectDialogState extends State<_SubjectDialog> {
  final _name = TextEditingController();
  final _code = TextEditingController();
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _name.dispose();
    _code.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_name.text.trim().isEmpty) {
      setState(() => _error = 'اسم المادة مطلوب.');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await context.read<AcademicService>().createSubject(
            name: _name.text.trim(),
            code: _code.text.trim().isEmpty ? null : _code.text.trim(),
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
      title: const Text('مادة جديدة'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          TextField(
              controller: _name,
              decoration: const InputDecoration(labelText: 'اسم المادة')),
          TextField(
              controller: _code,
              decoration: const InputDecoration(labelText: 'الرمز (اختياري)')),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Text(_error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error)),
            ),
        ],
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

class _ClassDialog extends StatefulWidget {
  const _ClassDialog();
  @override
  State<_ClassDialog> createState() => _ClassDialogState();
}

class _ClassDialogState extends State<_ClassDialog> {
  final _name = TextEditingController();
  final _grade = TextEditingController();
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _name.dispose();
    _grade.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final grade = int.tryParse(_grade.text.trim());
    if (_name.text.trim().isEmpty || grade == null) {
      setState(() => _error = 'أدخل اسم الفصل ورقم الصف.');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await context
          .read<AcademicService>()
          .createClass(name: _name.text.trim(), grade: grade);
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
      title: const Text('فصل جديد'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          TextField(
              controller: _name,
              decoration: const InputDecoration(labelText: 'اسم الفصل')),
          TextField(
              controller: _grade,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(labelText: 'الصف (1-12)')),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Text(_error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error)),
            ),
        ],
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

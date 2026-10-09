import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/register.dart';
import '../models/teacher.dart';
import '../services/records_service.dart';
import '../services/teacher_service.dart';
import '../widgets/async_view.dart';

/// Teacher's daily attendance register: pick one of their assignments, a date,
/// then mark every student present/absent/late/excused and save.
class AttendanceRegisterScreen extends StatefulWidget {
  const AttendanceRegisterScreen({super.key});

  @override
  State<AttendanceRegisterScreen> createState() =>
      _AttendanceRegisterScreenState();
}

class _AttendanceRegisterScreenState extends State<AttendanceRegisterScreen> {
  static const _statuses = <String, String>{
    'present': 'حاضر',
    'absent': 'غائب',
    'late': 'متأخر',
    'excused': 'بعذر',
  };

  late Future<List<TeachingAssignment>> _assignments;
  TeachingAssignment? _assignment;
  DateTime _date = DateTime.now();
  Future<List<RegisterEntry>>? _entries;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _assignments = context.read<TeacherService>().myAssignments();
  }

  String get _dateStr =>
      '${_date.year.toString().padLeft(4, '0')}-${_date.month.toString().padLeft(2, '0')}-${_date.day.toString().padLeft(2, '0')}';

  Future<void> _loadRegister() async {
    final assignment = _assignment;
    final sectionId = assignment?.sectionId;
    if (sectionId == null) return;
    setState(() => _entries = _build(sectionId));
  }

  Future<List<RegisterEntry>> _build(int sectionId) async {
    final records = context.read<RecordsService>();
    final existing = await records.attendanceSession(
        sectionId: sectionId, date: _dateStr);
    if (existing != null && existing.entries.isNotEmpty) {
      return existing.entries;
    }
    final students = await records.sectionStudents(sectionId);
    return [
      for (final s in students)
        RegisterEntry(
          studentId: s.id,
          fullName: s.fullName,
          studentNumber: s.studentNumber,
        ),
    ];
  }

  Future<void> _save(List<RegisterEntry> entries) async {
    final sectionId = _assignment?.sectionId;
    if (sectionId == null) return;
    setState(() => _busy = true);
    try {
      await context.read<RecordsService>().takeRegister(
            sectionId: sectionId,
            date: _dateStr,
            entries: entries,
          );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('تم تسجيل الحضور.')),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text('تعذّر الحفظ: $e')));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _date,
      firstDate: DateTime(2020),
      lastDate: DateTime(2100),
    );
    if (picked != null) {
      setState(() => _date = picked);
      _loadRegister();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('تسجيل الحضور')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(12),
            child: Column(
              children: [
                AsyncView<List<TeachingAssignment>>(
                  future: _assignments,
                  onRetry: () => setState(() =>
                      _assignments = context.read<TeacherService>().myAssignments()),
                  builder: (context, assignments) => DropdownButtonFormField<int>(
                    initialValue: _assignment?.id,
                    isExpanded: true,
                    decoration: const InputDecoration(
                        labelText: 'المادة والشعبة',
                        border: OutlineInputBorder()),
                    items: [
                      for (final a in assignments)
                        DropdownMenuItem(value: a.id, child: Text(a.label)),
                    ],
                    onChanged: (id) {
                      final match =
                          assignments.where((a) => a.id == id).cast<TeachingAssignment?>();
                      setState(() => _assignment = match.isEmpty ? null : match.first);
                      _loadRegister();
                    },
                  ),
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: _pickDate,
                        icon: const Icon(Icons.calendar_today_outlined),
                        label: Text(_dateStr),
                      ),
                    ),
                    const SizedBox(width: 8),
                    IconButton.filledTonal(
                      onPressed: _loadRegister,
                      icon: const Icon(Icons.refresh),
                    ),
                  ],
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: _assignment?.sectionId == null
                ? const Center(child: Text('اختر مادة وشعبة للبدء.'))
                : _entries == null
                    ? const Center(child: Text('اضغط تحديث لتحميل الكشف.'))
                    : AsyncView<List<RegisterEntry>>(
                        future: _entries!,
                        onRetry: _loadRegister,
                        builder: (context, entries) => entries.isEmpty
                            ? const Center(child: Text('لا يوجد طلاب في هذه الشعبة.'))
                            : Column(
                                children: [
                                  Expanded(
                                    child: ListView.builder(
                                      itemCount: entries.length,
                                      itemBuilder: (context, i) {
                                        final e = entries[i];
                                        return Card(
                                          child: ListTile(
                                            title: Text(e.fullName),
                                            subtitle: Text(e.studentNumber ?? ''),
                                            trailing: DropdownButton<String>(
                                              value: e.status,
                                              items: [
                                                for (final s in _statuses.entries)
                                                  DropdownMenuItem(
                                                      value: s.key,
                                                      child: Text(s.value)),
                                              ],
                                              onChanged: (v) => setState(
                                                  () => e.status = v ?? 'present'),
                                            ),
                                          ),
                                        );
                                      },
                                    ),
                                  ),
                                  SafeArea(
                                    child: Padding(
                                      padding: const EdgeInsets.all(12),
                                      child: SizedBox(
                                        width: double.infinity,
                                        child: FilledButton.icon(
                                          onPressed:
                                              _busy ? null : () => _save(entries),
                                          icon: _busy
                                              ? const SizedBox(
                                                  width: 18,
                                                  height: 18,
                                                  child:
                                                      CircularProgressIndicator())
                                              : const Icon(Icons.save_outlined),
                                          label: const Text('حفظ الكشف'),
                                        ),
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                      ),
          ),
        ],
      ),
    );
  }
}

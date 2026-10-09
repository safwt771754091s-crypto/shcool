import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/exam.dart';
import '../models/grade.dart';
import '../models/teacher.dart';
import '../services/records_service.dart';
import '../services/teacher_service.dart';
import '../widgets/async_view.dart';

/// Teacher's grade-entry sheet: pick one of their assignments, then an exam for
/// that (subject, section), and record each student's mark or mark them absent.
class GradeEntryScreen extends StatefulWidget {
  const GradeEntryScreen({super.key});

  @override
  State<GradeEntryScreen> createState() => _GradeEntryScreenState();
}

class _GradeEntryScreenState extends State<GradeEntryScreen> {
  late Future<List<TeachingAssignment>> _assignments;
  TeachingAssignment? _assignment;
  late Future<List<Exam>> _exams;
  Exam? _exam;
  Future<List<GradeEntry>>? _rows;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _assignments = context.read<TeacherService>().myAssignments();
    _exams = Future.value(const []);
  }

  void _selectAssignment(TeachingAssignment? a) {
    setState(() {
      _assignment = a;
      _exam = null;
      _rows = null;
      _exams = a?.sectionId == null
          ? Future.value(const [])
          : context.read<RecordsService>().exams(
                sectionId: a!.sectionId,
                subjectId: a.subjectId,
              );
    });
  }

  Future<void> _loadRows(Exam exam) async {
    setState(() {
      _exam = exam;
      _rows = _build(exam);
    });
  }

  Future<List<GradeEntry>> _build(Exam exam) async {
    final records = context.read<RecordsService>();
    final existing = await records.examGrades(exam.id);
    final byStudent = {for (final g in existing) g.studentId: g};

    final sectionId = exam.sectionId ?? _assignment?.sectionId;
    if (sectionId == null) return existing;

    final students = await records.sectionStudents(sectionId);
    return [
      for (final s in students)
        byStudent[s.id] ??
            GradeEntry(
              studentId: s.id,
              fullName: s.fullName,
              studentNumber: s.studentNumber,
            ),
    ];
  }

  Future<void> _save(List<GradeEntry> rows) async {
    final exam = _exam;
    if (exam == null) return;
    setState(() => _busy = true);
    try {
      await context
          .read<RecordsService>()
          .recordGrades(examId: exam.id, rows: [
        for (final r in rows) r.toPayload(),
      ]);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('تم رصد الدرجات.')),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text('تعذّر الحفظ: $e')));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('رصد الدرجات')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(12),
            child: Column(
              children: [
                AsyncView<List<TeachingAssignment>>(
                  future: _assignments,
                  onRetry: () => setState(() => _assignments =
                      context.read<TeacherService>().myAssignments()),
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
                      final match = assignments
                          .where((a) => a.id == id)
                          .cast<TeachingAssignment?>();
                      _selectAssignment(match.isEmpty ? null : match.first);
                    },
                  ),
                ),
                const SizedBox(height: 8),
                AsyncView<List<Exam>>(
                  future: _exams,
                  onRetry: () {},
                  builder: (context, exams) => DropdownButtonFormField<int>(
                    initialValue: _exam?.id,
                    isExpanded: true,
                    decoration: const InputDecoration(
                        labelText: 'الاختبار', border: OutlineInputBorder()),
                    items: [
                      for (final e in exams)
                        DropdownMenuItem(
                            value: e.id, child: Text('${e.title} (${e.typeLabel})')),
                    ],
                    onChanged: (id) {
                      if (id == null) return;
                      final match = exams.where((e) => e.id == id);
                      if (match.isNotEmpty) _loadRows(match.first);
                    },
                  ),
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: _rows == null
                ? const Center(child: Text('اختر المادة ثم الاختبار لبدء الرصد.'))
                : AsyncView<List<GradeEntry>>(
                    future: _rows!,
                    onRetry: () => _exam == null ? null : _loadRows(_exam!),
                    builder: (context, rows) => rows.isEmpty
                        ? const Center(child: Text('لا يوجد طلاب في هذه الشعبة.'))
                        : Column(
                            children: [
                              Expanded(
                                child: ListView.builder(
                                  itemCount: rows.length,
                                  itemBuilder: (context, i) {
                                    final r = rows[i];
                                    return Card(
                                      child: ListTile(
                                        title: Text(r.fullName),
                                        subtitle: Text(r.studentNumber ?? ''),
                                        trailing: Row(
                                          mainAxisSize: MainAxisSize.min,
                                          children: [
                                            SizedBox(
                                              width: 90,
                                              child: TextFormField(
                                                key: ValueKey(
                                                    'mark-${r.studentId}'),
                                                initialValue: r.mark?.toString() ??
                                                    '',
                                                enabled: !r.isAbsent,
                                                keyboardType:
                                                    const TextInputType
                                                        .numberWithOptions(
                                                        decimal: true),
                                                decoration: const InputDecoration(
                                                    labelText: 'الدرجة',
                                                    isDense: true),
                                                onChanged: (v) => r.mark =
                                                    double.tryParse(v.trim()),
                                              ),
                                            ),
                                            Checkbox(
                                              value: r.isAbsent,
                                              onChanged: (v) => setState(() =>
                                                  r.isAbsent = v ?? false),
                                            ),
                                          ],
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
                                          _busy ? null : () => _save(rows),
                                      icon: _busy
                                          ? const SizedBox(
                                              width: 18,
                                              height: 18,
                                              child: CircularProgressIndicator())
                                          : const Icon(Icons.save_outlined),
                                      label: const Text('حفظ الدرجات'),
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

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/academic.dart';
import '../models/attendance.dart';
import '../services/records_service.dart';
import '../widgets/async_view.dart';
import '../widgets/section_picker.dart';

/// Attendance report for a section over a date range, plus absence alerts.
class AttendanceReportScreen extends StatefulWidget {
  const AttendanceReportScreen({super.key});

  @override
  State<AttendanceReportScreen> createState() => _AttendanceReportScreenState();
}

class _AttendanceReportScreenState extends State<AttendanceReportScreen> {
  ClassSection? _section;
  late final TextEditingController _from;
  late final TextEditingController _to;
  Future<List<AttendanceReportRow>>? _future;

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    final first = DateTime(now.year, now.month, 1);
    _from = TextEditingController(text: _fmt(first));
    _to = TextEditingController(text: _fmt(now));
  }

  @override
  void dispose() {
    _from.dispose();
    _to.dispose();
    super.dispose();
  }

  static String _fmt(DateTime d) =>
      '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  void _run() {
    final section = _section;
    if (section == null) return;
    setState(() {
      _future = context.read<RecordsService>().attendanceReport(
            sectionId: section.id,
            from: _from.text.trim(),
            to: _to.text.trim(),
          );
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('تقرير الحضور والغياب')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(12),
            child: Column(
              children: [
                SectionPicker(
                  label: 'الشعبة',
                  onChanged: (s) => setState(() => _section = s),
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    Expanded(
                      child: TextField(
                        controller: _from,
                        decoration: const InputDecoration(
                            labelText: 'من', border: OutlineInputBorder()),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: TextField(
                        controller: _to,
                        decoration: const InputDecoration(
                            labelText: 'إلى', border: OutlineInputBorder()),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                SizedBox(
                  width: double.infinity,
                  child: FilledButton.icon(
                    onPressed: _section == null ? null : _run,
                    icon: const Icon(Icons.search),
                    label: const Text('عرض التقرير'),
                  ),
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: _future == null
                ? const Center(child: Text('اختر شعبة ثم اعرض التقرير.'))
                : AsyncView<List<AttendanceReportRow>>(
                    future: _future!,
                    onRetry: _run,
                    builder: (context, rows) => rows.isEmpty
                        ? const Center(child: Text('لا توجد سجلات في هذه الفترة.'))
                        : ListView.builder(
                            itemCount: rows.length,
                            itemBuilder: (context, i) {
                              final r = rows[i];
                              return Card(
                                child: ListTile(
                                  leading: CircleAvatar(
                                      child: Text('${r.rate.round()}%')),
                                  title: Text(r.fullName),
                                  subtitle: Text(
                                      'حضور ${r.present} • غياب ${r.absent} • تأخير ${r.late}'),
                                  trailing: Text('${r.total} يوم'),
                                ),
                              );
                            },
                          ),
                  ),
          ),
        ],
      ),
    );
  }
}

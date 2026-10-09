import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/academic.dart';
import '../models/exam.dart';
import '../services/records_service.dart';
import '../widgets/async_view.dart';
import '../widgets/section_picker.dart';

/// Exams for a section with the ranked result sheet.
class ExamsScreen extends StatefulWidget {
  const ExamsScreen({super.key});

  @override
  State<ExamsScreen> createState() => _ExamsScreenState();
}

class _ExamsScreenState extends State<ExamsScreen> {
  ClassSection? _section;
  Future<List<Exam>>? _exams;
  Future<List<ResultSheet>>? _results;

  void _load() {
    final section = _section;
    if (section == null) return;
    setState(() {
      _exams = context.read<RecordsService>().exams(sectionId: section.id);
      _results = context
          .read<RecordsService>()
          .sectionResultSheet(sectionId: section.id);
    });
  }

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('الاختبارات والنتائج'),
          bottom: const TabBar(tabs: [
            Tab(text: 'الاختبارات'),
            Tab(text: 'كشف النتائج'),
          ]),
        ),
        body: Column(
          children: [
            Padding(
              padding: const EdgeInsets.all(12),
              child: SectionPicker(
                label: 'الشعبة',
                onChanged: (s) {
                  _section = s;
                  _load();
                },
              ),
            ),
            Expanded(
              child: TabBarView(
                children: [
                  _exams == null
                      ? const Center(child: Text('اختر شعبة لعرض الاختبارات.'))
                      : AsyncView<List<Exam>>(
                          future: _exams!,
                          onRetry: _load,
                          builder: (context, exams) => exams.isEmpty
                              ? const Center(child: Text('لا توجد اختبارات.'))
                              : ListView.builder(
                                  itemCount: exams.length,
                                  itemBuilder: (context, i) {
                                    final e = exams[i];
                                    return Card(
                                      child: ListTile(
                                        leading: const CircleAvatar(
                                            child: Icon(Icons.quiz_outlined)),
                                        title: Text(e.title),
                                        subtitle: Text([
                                          if (e.subject != null) e.subject!,
                                          e.typeLabel,
                                          if (e.heldOn != null) e.heldOn!,
                                        ].join('  •  ')),
                                        trailing: Text(
                                            '${e.gradesCount} درجة'),
                                      ),
                                    );
                                  },
                                ),
                        ),
                  _results == null
                      ? const Center(child: Text('اختر شعبة لعرض النتائج.'))
                      : AsyncView<List<ResultSheet>>(
                          future: _results!,
                          onRetry: _load,
                          builder: (context, sheets) => sheets.isEmpty
                              ? const Center(child: Text('لا توجد نتائج.'))
                              : ListView.builder(
                                  itemCount: sheets.length,
                                  itemBuilder: (context, i) {
                                    final s = sheets[i];
                                    return Card(
                                      child: ListTile(
                                        leading: CircleAvatar(
                                          child: Text(s.rank == null
                                              ? '-'
                                              : '${s.rank}'),
                                        ),
                                        title: Text(s.fullName),
                                        subtitle:
                                            Text('المعدل ${s.overallAverage.toStringAsFixed(1)}'),
                                      ),
                                    );
                                  },
                                ),
                        ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

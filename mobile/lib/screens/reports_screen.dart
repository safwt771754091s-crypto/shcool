import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/finance.dart';
import '../services/finance_service.dart';
import '../widgets/async_view.dart';
import '../widgets/report_table_view.dart';

/// General reports with a type picker; each report is rendered as a table.
class ReportsScreen extends StatefulWidget {
  const ReportsScreen({super.key});

  @override
  State<ReportsScreen> createState() => _ReportsScreenState();
}

class _ReportsScreenState extends State<ReportsScreen> {
  static const _types = <String, String>{
    'students': 'تقرير الطلاب',
    'teachers': 'تقرير المعلمين',
    'attendance': 'تقرير الحضور',
    'absence-alerts': 'تنبيهات الغياب',
    'results': 'النتائج',
    'invoices': 'الفواتير',
  };

  String _type = 'students';
  late Future<ReportTable> _future;
  bool _exporting = false;

  @override
  void initState() {
    super.initState();
    _future = context.read<FinanceService>().report(_type);
  }

  void _reload() =>
      setState(() => _future = context.read<FinanceService>().report(_type));

  Future<void> _export(String format) async {
    setState(() => _exporting = true);
    try {
      final bytes = await context.read<FinanceService>().export(
            _type,
            format: format,
          );
      final fileName = '$_type-${DateTime.now().millisecondsSinceEpoch}.$format';
      final mime = switch (format) {
        'pdf' => 'application/pdf',
        'xlsx' =>
          'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        _ => 'text/csv',
      };
      await FilePicker.saveFile(
        fileName: fileName,
        bytes: Uint8List.fromList(bytes),
        mimeType: mime,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text('تم تصدير التقرير ($format).')));
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text('تعذّر التصدير: $e')));
    } finally {
      if (mounted) setState(() => _exporting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('التقارير'),
        actions: [
          PopupMenuButton<String>(
            enabled: !_exporting,
            tooltip: 'تصدير',
            icon: _exporting
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(strokeWidth: 2))
                : const Icon(Icons.download_outlined),
            onSelected: _export,
            itemBuilder: (context) => const [
              PopupMenuItem(value: 'pdf', child: Text('PDF')),
              PopupMenuItem(value: 'xlsx', child: Text('Excel (XLSX)')),
              PopupMenuItem(value: 'csv', child: Text('CSV')),
            ],
          ),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(12),
            child: DropdownButtonFormField<String>(
              initialValue: _type,
              decoration: const InputDecoration(
                  labelText: 'نوع التقرير', border: OutlineInputBorder()),
              items: [
                for (final e in _types.entries)
                  DropdownMenuItem(value: e.key, child: Text(e.value)),
              ],
              onChanged: (v) {
                if (v == null) return;
                setState(() {
                  _type = v;
                  _future = context.read<FinanceService>().report(v);
                });
              },
            ),
          ),
          Expanded(
            child: AsyncView<ReportTable>(
              future: _future,
              onRetry: _reload,
              builder: (context, table) => Padding(
                padding: const EdgeInsets.symmetric(horizontal: 8),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Padding(
                      padding: const EdgeInsets.all(8),
                      child: Text(table.title,
                          style: Theme.of(context).textTheme.titleLarge),
                    ),
                    Expanded(child: ReportTableView(table: table)),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

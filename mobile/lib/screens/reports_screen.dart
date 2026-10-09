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

  @override
  void initState() {
    super.initState();
    _future = context.read<FinanceService>().report(_type);
  }

  void _reload() =>
      setState(() => _future = context.read<FinanceService>().report(_type));

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('التقارير')),
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

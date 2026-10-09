import 'package:flutter/material.dart';

import '../models/finance.dart';

/// Renders a generic `{title, headers[], rows[]}` report as a scrollable table.
class ReportTableView extends StatelessWidget {
  const ReportTableView({super.key, required this.table});

  final ReportTable table;

  @override
  Widget build(BuildContext context) {
    if (table.headers.isEmpty) {
      return const Center(child: Text('لا توجد بيانات.'));
    }
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: SingleChildScrollView(
        child: DataTable(
          columns: [
            for (final h in table.headers)
              DataColumn(label: Text(h, style: const TextStyle(fontWeight: FontWeight.bold))),
          ],
          rows: [
            for (final row in table.rows)
              DataRow(cells: [
                for (final value in row.values) DataCell(Text('$value')),
              ]),
          ],
        ),
      ),
    );
  }
}

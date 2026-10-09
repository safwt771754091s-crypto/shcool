import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/finance.dart';
import '../services/finance_service.dart';
import '../widgets/async_view.dart';
import '../widgets/stat_card.dart';

/// Finance: collection summary, invoices and fee structures.
class FinanceScreen extends StatefulWidget {
  const FinanceScreen({super.key});

  @override
  State<FinanceScreen> createState() => _FinanceScreenState();
}

class _FinanceScreenState extends State<FinanceScreen> {
  late Future<FinanceSummary> _summary;
  late Future<List<Invoice>> _invoices;
  late Future<List<FeeStructure>> _fees;

  @override
  void initState() {
    super.initState();
    _load();
  }

  void _load() {
    final svc = context.read<FinanceService>();
    _summary = svc.summary();
    _invoices = svc.invoices();
    _fees = svc.fees();
  }

  void _reload() => setState(_load);

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 3,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('الرسوم والمدفوعات'),
          actions: [
            IconButton(onPressed: _reload, icon: const Icon(Icons.refresh)),
          ],
          bottom: const TabBar(tabs: [
            Tab(text: 'الملخّص'),
            Tab(text: 'الفواتير'),
            Tab(text: 'الرسوم'),
          ]),
        ),
        body: TabBarView(
          children: [
            AsyncView<FinanceSummary>(
              future: _summary,
              onRetry: _reload,
              builder: (context, s) => ListView(
                padding: const EdgeInsets.all(12),
                children: [
                  GridView.count(
                    crossAxisCount: 2,
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    mainAxisSpacing: 8,
                    crossAxisSpacing: 8,
                    childAspectRatio: 1.5,
                    children: [
                      StatCard(
                          label: 'إجمالي المفوتر',
                          value: s.invoiced.toStringAsFixed(0),
                          icon: Icons.receipt_long_outlined),
                      StatCard(
                          label: 'المُحصَّل',
                          value: s.collected.toStringAsFixed(0),
                          icon: Icons.payments_outlined,
                          color: Colors.green.shade700),
                      StatCard(
                          label: 'المتبقّي',
                          value: s.outstanding.toStringAsFixed(0),
                          icon: Icons.money_off_outlined,
                          color: Colors.orange.shade800),
                      StatCard(
                          label: 'فواتير غير مدفوعة',
                          value: '${s.invoices['unpaid'] ?? 0}',
                          icon: Icons.warning_amber_outlined,
                          color: Colors.red.shade700),
                    ],
                  ),
                ],
              ),
            ),
            AsyncView<List<Invoice>>(
              future: _invoices,
              onRetry: _reload,
              builder: (context, invoices) => invoices.isEmpty
                  ? const Center(child: Text('لا توجد فواتير.'))
                  : ListView.builder(
                      itemCount: invoices.length,
                      itemBuilder: (context, i) {
                        final inv = invoices[i];
                        return Card(
                          child: ListTile(
                            leading: const CircleAvatar(
                                child: Icon(Icons.description_outlined)),
                            title: Text(inv.number),
                            subtitle: Text(inv.studentName ?? ''),
                            trailing: Column(
                              mainAxisAlignment: MainAxisAlignment.center,
                              crossAxisAlignment: CrossAxisAlignment.end,
                              children: [
                                Text(
                                    '${inv.balance.toStringAsFixed(0)} ${inv.currency}'),
                                Text(inv.statusLabel,
                                    style: Theme.of(context).textTheme.bodySmall),
                              ],
                            ),
                          ),
                        );
                      },
                    ),
            ),
            AsyncView<List<FeeStructure>>(
              future: _fees,
              onRetry: _reload,
              builder: (context, fees) => fees.isEmpty
                  ? const Center(child: Text('لا توجد رسوم مُعرَّفة.'))
                  : ListView.builder(
                      itemCount: fees.length,
                      itemBuilder: (context, i) {
                        final f = fees[i];
                        return Card(
                          child: ListTile(
                            leading: const CircleAvatar(
                                child: Icon(Icons.request_quote_outlined)),
                            title: Text(f.name),
                            subtitle: Text([
                              f.typeLabel,
                              if (f.className != null) f.className!,
                            ].join('  •  ')),
                            trailing: Text(
                                '${f.amount.toStringAsFixed(0)} ${f.currency}'),
                          ),
                        );
                      },
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

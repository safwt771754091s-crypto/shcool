import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/competition.dart';
import '../services/competition_service.dart';
import '../widgets/async_view.dart';

/// National ranking dashboard: pick a ranking period and a scope (schools,
/// governorates, students, ...) and show the ranked table with movement.
class NationalRankingScreen extends StatefulWidget {
  const NationalRankingScreen({super.key});

  @override
  State<NationalRankingScreen> createState() => _NationalRankingScreenState();
}

class _NationalRankingScreenState extends State<NationalRankingScreen> {
  static const _scopes = <String, String>{
    'school': 'المدارس',
    'directorate': 'المديريات',
    'governorate': 'المحافظات',
    'class': 'الشعب',
    'student': 'الطلاب',
    'ministry': 'الوزارة',
  };

  late Future<List<RankingPeriod>> _periods;
  RankingPeriod? _period;
  String _scope = 'school';
  Future<List<LeaderboardEntry>>? _entries;

  @override
  void initState() {
    super.initState();
    _periods = context.read<CompetitionService>().periods();
  }

  void _loadBoard() {
    final period = _period;
    if (period == null) return;
    setState(() {
      _entries = context
          .read<CompetitionService>()
          .leaderboard(periodId: period.id, scope: _scope, limit: 100);
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('الترتيب الوطني'),
        actions: [
          IconButton(
            tooltip: 'إعادة الاحتساب من البيانات الأكاديمية',
            icon: const Icon(Icons.calculate_outlined),
            onPressed: _period == null
                ? null
                : () async {
                    await context
                        .read<CompetitionService>()
                        .recomputeFromAcademics(_period!.id);
                    if (mounted) _loadBoard();
                  },
          ),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(12),
            child: AsyncView<List<RankingPeriod>>(
              future: _periods,
              onRetry: () => setState(
                  () => _periods = context.read<CompetitionService>().periods()),
              builder: (context, periods) {
                if (periods.isEmpty) {
                  return const Card(
                    child: Padding(
                      padding: EdgeInsets.all(16),
                      child: Text('لا توجد فترات ترتيب بعد. أنشئ فترة أولاً.'),
                    ),
                  );
                }
                return Column(
                  children: [
                    DropdownButtonFormField<int>(
                      initialValue: _period?.id,
                      isExpanded: true,
                      decoration: const InputDecoration(
                          labelText: 'فترة الترتيب',
                          border: OutlineInputBorder()),
                      items: [
                        for (final p in periods)
                          DropdownMenuItem(
                              value: p.id, child: Text(p.name)),
                      ],
                      onChanged: (id) {
                        final match = periods
                            .where((p) => p.id == id)
                            .cast<RankingPeriod?>();
                        _period = match.isEmpty ? null : match.first;
                        _loadBoard();
                      },
                    ),
                    const SizedBox(height: 8),
                    DropdownButtonFormField<String>(
                      initialValue: _scope,
                      isExpanded: true,
                      decoration: const InputDecoration(
                          labelText: 'النطاق', border: OutlineInputBorder()),
                      items: [
                        for (final e in _scopes.entries)
                          DropdownMenuItem(value: e.key, child: Text(e.value)),
                      ],
                      onChanged: (v) {
                        if (v == null) return;
                        _scope = v;
                        _loadBoard();
                      },
                    ),
                  ],
                );
              },
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: _entries == null
                ? const Center(child: Text('اختر فترة ونطاقاً لعرض الترتيب.'))
                : AsyncView<List<LeaderboardEntry>>(
                    future: _entries!,
                    onRetry: _loadBoard,
                    builder: (context, entries) => entries.isEmpty
                        ? const Center(
                            child: Text('لا توجد نتائج مُحتسبة لهذه الفترة بعد.'))
                        : ListView.builder(
                            itemCount: entries.length,
                            itemBuilder: (context, i) {
                              final e = entries[i];
                              return Card(
                                child: ListTile(
                                  leading: _RankBadge(rank: e.rank),
                                  title: Text(e.name),
                                  subtitle: Text(
                                      '${e.totalPoints.toStringAsFixed(1)} نقطة'),
                                  trailing: _DeltaChip(delta: e.rankDelta),
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

class _RankBadge extends StatelessWidget {
  const _RankBadge({required this.rank});
  final int rank;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final color = switch (rank) {
      1 => const Color(0xFFD4AF37),
      2 => const Color(0xFFB0BEC5),
      3 => const Color(0xFFCD7F32),
      _ => scheme.surfaceContainerHighest,
    };
    return CircleAvatar(
      backgroundColor: color,
      child: Text('$rank',
          style: TextStyle(
              fontWeight: FontWeight.bold,
              color: rank <= 3 ? Colors.white : scheme.onSurface)),
    );
  }
}

class _DeltaChip extends StatelessWidget {
  const _DeltaChip({this.delta});
  final int? delta;

  @override
  Widget build(BuildContext context) {
    if (delta == null || delta == 0) {
      return const Icon(Icons.remove, color: Colors.grey);
    }
    final up = delta! > 0;
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(up ? Icons.arrow_upward : Icons.arrow_downward,
            size: 16, color: up ? Colors.green : Colors.red),
        Text('${delta!.abs()}',
            style: TextStyle(color: up ? Colors.green : Colors.red)),
      ],
    );
  }
}

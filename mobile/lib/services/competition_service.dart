import '../core/network/api_client.dart';
import '../models/competition.dart';

/// Competition: ranking periods and the national leaderboards.
class CompetitionService {
  CompetitionService(this._api);

  final ApiClient _api;

  Future<List<RankingPeriod>> periods({bool activeOnly = false}) async {
    final data = await _api.get('ranking-periods',
        query: activeOnly ? {'active_only': 1} : null) as Map<String, dynamic>;
    final payload = data['data'] as List? ?? const [];
    return payload
        .map((e) => RankingPeriod.fromJson((e as Map).cast<String, dynamic>()))
        .toList();
  }

  /// [scope] is one of student|class|school|directorate|governorate|ministry.
  Future<List<LeaderboardEntry>> leaderboard({
    required int periodId,
    required String scope,
    int? limit,
  }) async {
    final data = await _api.get('ranking-periods/$periodId/leaderboard',
        query: {'scope': scope, 'limit': ?limit}) as Map<String, dynamic>;
    final payload = data['data'] as List? ?? const [];
    return payload
        .map((e) =>
            LeaderboardEntry.fromJson((e as Map).cast<String, dynamic>()))
        .toList();
  }

  Future<void> recomputeFromAcademics(int periodId) async {
    await _api.post('ranking-periods/$periodId/recompute-academics');
  }
}

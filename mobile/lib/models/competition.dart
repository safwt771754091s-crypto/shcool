/// Competition / national-ranking models.
library;

class RankingPeriod {
  RankingPeriod({
    required this.id,
    required this.name,
    this.type,
    this.startsOn,
    this.endsOn,
    this.isActive = false,
    this.isLocked = false,
  });

  final int id;
  final String name;
  final String? type;
  final String? startsOn;
  final String? endsOn;
  final bool isActive;
  final bool isLocked;

  String get typeLabel => switch (type) {
        'term' => 'فصل دراسي',
        'semester' => 'نصف سنة',
        'month' => 'شهري',
        'year' => 'سنوي',
        _ => 'مخصّص',
      };

  factory RankingPeriod.fromJson(Map<String, dynamic> json) => RankingPeriod(
        id: (json['id'] as num).toInt(),
        name: json['name']?.toString() ?? '',
        type: json['type'] as String?,
        startsOn: json['starts_on'] as String?,
        endsOn: json['ends_on'] as String?,
        isActive: json['is_active'] == true,
        isLocked: json['is_locked'] == true,
      );
}

class LeaderboardEntry {
  LeaderboardEntry({
    required this.scopeType,
    required this.name,
    this.scopeId,
    this.totalPoints = 0,
    this.rank = 0,
    this.previousRank,
    this.sampleSize = 0,
  });

  final String scopeType;
  final String name;
  final int? scopeId;
  final double totalPoints;
  final int rank;
  final int? previousRank;
  final int sampleSize;

  /// Positive when the entity climbed (a smaller rank is better).
  int? get rankDelta =>
      previousRank == null ? null : previousRank! - rank;

  String get scopeLabel => switch (scopeType) {
        'student' => 'طالب',
        'class' => 'شعبة',
        'school' => 'مدرسة',
        'directorate' => 'مديرية',
        'governorate' => 'محافظة',
        'ministry' => 'وزارة',
        _ => scopeType,
      };

  factory LeaderboardEntry.fromJson(Map<String, dynamic> json) =>
      LeaderboardEntry(
        scopeType: json['scope_type']?.toString() ?? '',
        name: json['name']?.toString() ?? '',
        scopeId: (json['scope_id'] as num?)?.toInt(),
        totalPoints: (json['total_points'] as num?)?.toDouble() ?? 0,
        rank: (json['rank'] as num?)?.toInt() ?? 0,
        previousRank: (json['previous_rank'] as num?)?.toInt(),
        sampleSize: (json['sample_size'] as num?)?.toInt() ?? 0,
      );
}

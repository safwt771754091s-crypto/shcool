<?php

namespace App\Support\Competition;

/**
 * The fixed ladder a tournament climbs:
 *
 *   sections -> classes -> schools -> directorates (مراكز التربية)
 *            -> governorates -> ministry final
 *
 * Both the sports league and the academic leaderboard reuse this ordering so
 * "what level is this entity" has a single answer across the platform.
 */
final class CompetitionScope
{
    public const SECTION = 'section';

    public const CLASS_LEVEL = 'class';

    public const SCHOOL = 'school';

    public const DIRECTORATE = 'directorate';

    public const GOVERNORATE = 'governorate';

    public const MINISTRY = 'ministry';

    /**
     * Ordered from the smallest unit to the national final.
     *
     * @var list<string>
     */
    public const LADDER = [
        self::SECTION,
        self::CLASS_LEVEL,
        self::SCHOOL,
        self::DIRECTORATE,
        self::GOVERNORATE,
        self::MINISTRY,
    ];

    /**
     * Map an organization type onto the ladder step it plays at.
     */
    public static function fromOrganizationType(string $type): string
    {
        return match ($type) {
            'ministry' => self::MINISTRY,
            'governorate' => self::GOVERNORATE,
            'directorate' => self::DIRECTORATE,
            'school' => self::SCHOOL,
            'branch' => self::SCHOOL,
            default => self::SECTION,
        };
    }

    public static function next(?string $scope): ?string
    {
        $index = array_search($scope, self::LADDER, true);

        if ($index === false || $index >= count(self::LADDER) - 1) {
            return null;
        }

        return self::LADDER[$index + 1];
    }

    public static function rank(string $scope): int
    {
        $index = array_search($scope, self::LADDER, true);

        return $index === false ? 0 : $index + 1;
    }

    public static function isFinal(string $scope): bool
    {
        return $scope === self::MINISTRY;
    }
}

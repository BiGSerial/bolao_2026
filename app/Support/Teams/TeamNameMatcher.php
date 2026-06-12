<?php

namespace App\Support\Teams;

use Illuminate\Support\Str;

final class TeamNameMatcher
{
    public static function matches(string $left, string $right): bool
    {
        $leftKey = self::aliasKey($left);
        $rightKey = self::aliasKey($right);

        if ($leftKey !== null && $rightKey !== null) {
            return $leftKey === $rightKey;
        }

        $leftNormalized = self::normalize($left);
        $rightNormalized = self::normalize($right);

        return $leftNormalized !== '' && $leftNormalized === $rightNormalized;
    }

    public static function aliasKey(string $value): ?string
    {
        $compact = self::compact($value);
        if ($compact === '') {
            return null;
        }

        foreach (self::aliases() as $key => $names) {
            foreach ($names as $name) {
                $alias = self::compact((string) $name);
                if ($compact === $alias) {
                    return (string) $key;
                }
            }
        }

        foreach ((array) config('team_aliases.clubs', []) as $key => $names) {
            foreach ((array) $names as $name) {
                $alias = self::compact((string) $name);
                if (strlen($alias) >= 5
                    && (str_contains($compact, $alias) || str_contains($alias, $compact))) {
                    return (string) $key;
                }
            }
        }

        return null;
    }

    public static function normalize(string $value): string
    {
        $normalized = mb_strtoupper(Str::ascii($value));
        $normalized = preg_replace('/[^A-Z0-9 ]/u', ' ', $normalized) ?? '';

        return trim(preg_replace('/\s+/', ' ', $normalized) ?? $normalized);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function worldCupAliases(): array
    {
        return (array) config('team_aliases.world_cup_2026', []);
    }

    private static function compact(string $value): string
    {
        return preg_replace('/[^A-Z0-9]/', '', self::normalize($value)) ?? '';
    }

    /**
     * @return array<string, array<int, string>>
     */
    private static function aliases(): array
    {
        return array_merge(
            self::worldCupAliases(),
            (array) config('team_aliases.clubs', [])
        );
    }
}

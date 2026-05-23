<?php

namespace App\Support\Api;

use App\Models\FootballMatch;

class MatchPayload
{
    public static function fromModel(FootballMatch $match): array
    {
        return [
            'id' => $match->id,
            'status' => (string) $match->status,
            'utc_date' => optional($match->utc_date)?->toIso8601String(),
            'local_date' => optional($match->kickoffAtBrazil())?->toIso8601String(),
            'stage' => $match->stage,
            'matchday' => $match->matchday,
            'competition' => [
                'id' => $match->competition?->id,
                'code' => $match->competition?->code,
                'name' => $match->competition?->name,
            ],
            'home_team' => [
                'id' => $match->homeTeam?->id,
                'name' => $match->homeTeam?->localized_name,
                'short_name' => $match->homeTeam?->short_name,
                'tla' => $match->homeTeam?->tla,
                'crest' => $match->homeTeam?->crest,
            ],
            'away_team' => [
                'id' => $match->awayTeam?->id,
                'name' => $match->awayTeam?->localized_name,
                'short_name' => $match->awayTeam?->short_name,
                'tla' => $match->awayTeam?->tla,
                'crest' => $match->awayTeam?->crest,
            ],
            'score' => [
                'home' => $match->home_score_full_time,
                'away' => $match->away_score_full_time,
            ],
        ];
    }
}

<?php

namespace App\Livewire\Dashboard;

use App\Models\FootballMatch;
use App\Models\PoolMember;
use App\Models\PoolRanking;
use App\Models\Standing;
use App\Models\StandingRow;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class Home extends Component
{
    #[On('echo:matches,MatchUpdated')]
    public function refreshMatches(): void {}

    public function render()
    {
        $userId = (int) Auth::id();
        $user = Auth::user();

        $currentCompetitionCode = strtoupper((string) request()->query(
            'competition',
            session('competition', config('football-data.default_competition_code', 'WC'))
        ));

        if (! $user || ! $user->canAccessCompetition($currentCompetitionCode)) {
            $currentCompetitionCode = 'WC';
        }

        session(['competition' => $currentCompetitionCode]);

        $currentSeason = (int) config('football-data.competitions.'.$currentCompetitionCode.'.season', 0);
        $currentStage = (string) config('football-data.competitions.'.$currentCompetitionCode.'.default_stage', 'GROUP_STAGE');
        $currentCompetitionName = (string) config('football-data.competitions.'.$currentCompetitionCode.'.name', $currentCompetitionCode);
        $standingsCriteriaLabel = match ($currentCompetitionCode) {
            'WC' => 'Critério FIFA',
            'BSA' => 'Pontos · Vitórias · SG · GP',
            default => 'Critério oficial',
        };

        $competitionScope = fn ($query) => $query
            ->whereHas('competition', fn ($q) => $q->where('code', $currentCompetitionCode))
            ->whereHas('season', fn ($q) => $q->where('year', $currentSeason));

        $myMemberships = PoolMember::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->with(['pool:id,name,slug,status,invite_code,competition_id,competition_season_id,stage'])
            ->latest('id')
            ->get();

        $myPoolIds = $myMemberships->pluck('pool.id')->filter()->all();

        $myRankings = PoolRanking::query()
            ->whereIn('pool_id', $myPoolIds)
            ->where('user_id', $userId)
            ->with('pool:id,name,slug')
            ->get()
            ->keyBy('pool_id');

        $upcoming = FootballMatch::query()
            ->where($competitionScope)
            ->whereIn('status', ['TIMED', 'SCHEDULED'])
            ->with(['homeTeam:id,name,short_name,tla,crest', 'awayTeam:id,name,short_name,tla,crest'])
            ->orderBy('utc_date')
            ->limit(5)
            ->get();

        $live = FootballMatch::query()
            ->where($competitionScope)
            ->whereIn('status', ['PRE_MATCH', 'IN_PLAY', 'PAUSED', 'EXTRA_TIME', 'PENALTY_SHOOTOUT'])
            ->with(['homeTeam:id,name,short_name,tla,crest', 'awayTeam:id,name,short_name,tla,crest'])
            ->orderBy('utc_date')
            ->get();

        $liveMinutes = [];
        foreach ($live as $match) {
            $liveMinutes[$match->id] = $this->resolveLiveMinute($match);
        }

        $recent = FootballMatch::query()
            ->where($competitionScope)
            ->where('status', 'FINISHED')
            ->with(['homeTeam:id,name,short_name,tla,crest', 'awayTeam:id,name,short_name,tla,crest', 'detail:id,football_match_id,payload'])
            ->orderByDesc('utc_date')
            ->limit(5)
            ->get();

        $matchLinks = [];
        $allMatches = $live->concat($upcoming)->concat($recent);
        foreach ($allMatches as $match) {
            $url = $this->resolveMatchUrlForUser($match);
            if ($url !== null) {
                $matchLinks[(int) $match->id] = $url;
            }
        }

        $standings = Standing::query()
            ->where($competitionScope)
            ->where('stage', $currentStage)
            ->where('type', 'TOTAL')
            ->with([
                'rows' => fn ($q) => $q->with('team:id,name,short_name,tla,crest')
                    ->orderByRaw('case when position is null then 1 else 0 end')
                    ->orderBy('position')
                    ->orderByDesc('points')
                    ->orderByDesc('goal_difference')
                    ->orderByDesc('goals_for'),
            ])
            ->orderByRaw('case when group_name is null then 1 else 0 end')
            ->orderBy('group_name')
            ->get();

        $groupStandings = $standings
            ->groupBy(fn (Standing $standing) => $standing->group_name ?: 'Classificação Geral')
            ->map(fn ($items) => $items->first())
            ->values();

        // Fallback da Copa: quando standings vier apenas como "Geral",
        // separa por grupos usando group_name dos jogos.
        if (
            $currentCompetitionCode === 'WC' &&
            $groupStandings->count() === 1 &&
            ($groupStandings->first()?->group_name === null || $groupStandings->first()?->group_name === 'Classificação Geral')
        ) {
            $teamGroupMap = [];
            $groupMatches = FootballMatch::query()
                ->where($competitionScope)
                ->whereNotNull('group_name')
                ->where('group_name', '!=', '')
                ->get(['home_team_id', 'away_team_id', 'group_name']);

            foreach ($groupMatches as $groupMatch) {
                if ($groupMatch->home_team_id) {
                    $teamGroupMap[$groupMatch->home_team_id] = $teamGroupMap[$groupMatch->home_team_id] ?? $groupMatch->group_name;
                }
                if ($groupMatch->away_team_id) {
                    $teamGroupMap[$groupMatch->away_team_id] = $teamGroupMap[$groupMatch->away_team_id] ?? $groupMatch->group_name;
                }
            }

            $baseStanding = $groupStandings->first();
            $rows = $baseStanding?->rows ?? collect();

            if (! empty($teamGroupMap) && $rows->isNotEmpty()) {
                $groupStandings = $rows
                    ->groupBy(fn (StandingRow $row) => $teamGroupMap[$row->team_id] ?? 'Classificação Geral')
                    ->map(function ($groupRows, string $groupName) {
                        $sortedRows = $groupRows
                            ->sortBy([
                                fn (StandingRow $row) => is_null($row->position) ? 1 : 0,
                                fn (StandingRow $row) => (int) ($row->position ?? PHP_INT_MAX),
                                fn (StandingRow $row) => -(int) $row->points,
                                fn (StandingRow $row) => -(int) $row->goal_difference,
                                fn (StandingRow $row) => -(int) $row->goals_for,
                            ])
                            ->values();

                        return (object) [
                            'group_name' => $groupName,
                            'rows' => $sortedRows,
                        ];
                    })
                    ->sortBy('group_name')
                    ->values();
            }
        }

        return view('livewire.dashboard.home', compact(
            'myMemberships',
            'myRankings',
            'upcoming',
            'live',
            'liveMinutes',
            'recent',
            'groupStandings',
            'currentCompetitionCode',
            'currentCompetitionName',
            'standingsCriteriaLabel',
            'matchLinks',
        ));
    }

    private function resolveMatchUrlForUser(FootballMatch $match): ?string
    {
        return route('matches.show', ['match' => $match->id]);
    }

    private function resolveLiveMinute(FootballMatch $match): ?int
    {
        if (! in_array($match->status, ['IN_PLAY', 'PAUSED', 'EXTRA_TIME', 'PENALTY_SHOOTOUT'], true)) {
            return null;
        }

        $apiShortStatus = strtoupper((string) data_get($match->raw_payload, 'api_football_status.short', ''));
        $apiElapsed = data_get($match->raw_payload, 'api_football_status.elapsed');
        if (in_array($apiShortStatus, ['PST', 'TBD', 'SUSP', 'INT', 'NS'], true) && ! is_numeric($apiElapsed)) {
            return null;
        }

        $minute = data_get($match->raw_payload, 'minute');
        if (is_numeric($minute)) {
            return max(1, min(130, (int) $minute));
        }

        $trackedSeconds = (int) ($match->live_clock_accumulated_seconds ?? 0);
        if ($match->status === 'IN_PLAY' && $match->live_clock_anchor_at) {
            $trackedSeconds += max(0, $match->live_clock_anchor_at->diffInSeconds(now()->utc()));
        }

        if ($trackedSeconds > 0) {
            return max(1, min(130, (int) floor($trackedSeconds / 60) + 1));
        }

        if (! $match->utc_date) {
            return null;
        }

        $kickoff = $match->utc_date->copy()->timezone('America/Sao_Paulo');
        $elapsed = $kickoff->diffInMinutes(now('America/Sao_Paulo'), false);
        if ($elapsed <= 0) {
            return 1;
        }

        return max(1, min(130, $elapsed + 1));
    }
}

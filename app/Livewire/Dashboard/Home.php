<?php

namespace App\Livewire\Dashboard;

use App\Models\FootballMatch;
use App\Models\Pool;
use App\Models\PoolMember;
use App\Models\PoolRanking;
use App\Models\Standing;
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

        $myMemberships = PoolMember::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->with(['pool:id,name,slug,status,invite_code'])
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
            ->where('status', 'TIMED')
            ->orWhere('status', 'SCHEDULED')
            ->with(['homeTeam:id,name,short_name,tla,crest', 'awayTeam:id,name,short_name,tla,crest'])
            ->orderBy('utc_date')
            ->limit(5)
            ->get();

        $live = FootballMatch::query()
            ->whereIn('status', ['IN_PLAY', 'PAUSED'])
            ->with(['homeTeam:id,name,short_name,tla,crest', 'awayTeam:id,name,short_name,tla,crest'])
            ->orderBy('utc_date')
            ->get();

        $recent = FootballMatch::query()
            ->where('status', 'FINISHED')
            ->with(['homeTeam:id,name,short_name,tla,crest', 'awayTeam:id,name,short_name,tla,crest'])
            ->orderByDesc('utc_date')
            ->limit(5)
            ->get();

        $groupStandings = Standing::query()
            ->where('stage', config('football-data.world_cup.stage'))
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
            ->get()
            ->groupBy(fn (Standing $standing) => $standing->group_name ?: 'Classificação Geral')
            ->map(fn ($items) => $items->first())
            ->values();

        return view('livewire.dashboard.home', compact(
            'myMemberships', 'myRankings', 'upcoming', 'live', 'recent', 'groupStandings'
        ));
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Standings;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\FootballMatch;
use App\Models\Standing;
use App\Support\ApiResponse;
use App\Support\Standings\StandingRowsSorter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StandingsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'competition_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $competitions = Competition::query()
            ->whereHas('standings')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'type']);

        if ($competitions->isEmpty()) {
            return ApiResponse::success($request, [
                'competitions' => [],
                'selected_competition_id' => null,
                'groups' => [],
            ]);
        }

        $selectedCompetitionId = (int) ($validated['competition_id'] ?? $competitions->first()->id);
        if (! $competitions->firstWhere('id', $selectedCompetitionId)) {
            $selectedCompetitionId = (int) $competitions->first()->id;
        }
        $selectedCompetition = $competitions->firstWhere('id', $selectedCompetitionId);
        $isCup = strtoupper((string) ($selectedCompetition?->type ?? '')) === 'CUP';

        if ($isCup) {
            $seasonId = Standing::query()
                ->where('competition_id', $selectedCompetitionId)
                ->whereNotNull('group_name')
                ->where('group_name', '!=', '')
                ->orderByDesc('competition_season_id')
                ->value('competition_season_id');

            if (! $seasonId) {
                $seasonId = Standing::query()
                    ->where('competition_id', $selectedCompetitionId)
                    ->orderByDesc('competition_season_id')
                    ->value('competition_season_id');
            }
        } else {
            $seasonId = Standing::query()
                ->where('competition_id', $selectedCompetitionId)
                ->where('type', 'TOTAL')
                ->orderByDesc('competition_season_id')
                ->value('competition_season_id');

            if (! $seasonId) {
                $seasonId = Standing::query()
                    ->where('competition_id', $selectedCompetitionId)
                    ->orderByDesc('competition_season_id')
                    ->value('competition_season_id');
            }
        }

        if (! $seasonId) {
            return ApiResponse::success($request, [
                'competitions' => $competitions->map(fn (Competition $c): array => [
                    'id' => (int) $c->id,
                    'code' => (string) $c->code,
                    'name' => (string) $c->name,
                    'type' => (string) ($c->type ?? ''),
                ])->values()->all(),
                'selected_competition_id' => $selectedCompetitionId,
                'groups' => [],
            ]);
        }

        $standingsQuery = Standing::query()
            ->where('competition_id', $selectedCompetitionId)
            ->where('competition_season_id', (int) $seasonId)
            ->with([
                'rows' => fn ($q) => $q->with('team:id,name,canonical_name_br,short_name,tla,crest')
                    ->orderBy('id'),
            ])
            ->orderByRaw('case when group_name is null then 1 else 0 end')
            ->orderBy('group_name');

        if ($isCup) {
            $standings = (clone $standingsQuery)
                ->whereNotNull('group_name')
                ->where('group_name', '!=', '')
                ->where('type', 'TOTAL')
                ->get();

            if ($standings->isEmpty()) {
                $standings = (clone $standingsQuery)
                    ->where('type', 'TOTAL')
                    ->get();
            }
        } else {
            $standings = (clone $standingsQuery)
                ->where('type', 'TOTAL')
                ->get();
        }

        $competitionCode = strtoupper((string) ($selectedCompetition?->code ?? ''));

        $groups = $standings->map(function (Standing $standing) use ($competitionCode): array {
            $rows = StandingRowsSorter::sort($standing->rows, $competitionCode);

            return [
                'name' => StandingRowsSorter::groupLabel($standing->group_name),
                'stage' => (string) ($standing->stage ?? ''),
                'rows' => $rows->map(fn ($row, int $index): array => [
                    'position' => $index + 1,
                    'team' => [
                        'id' => $row->team?->id,
                        'name' => $row->team?->localized_name,
                        'short_name' => $row->team?->canonical_name_br ?: $row->team?->short_name,
                        'tla' => $row->team?->tla,
                        'crest' => $row->team?->crest,
                    ],
                    'played_games' => (int) ($row->played_games ?? 0),
                    'won' => (int) ($row->won ?? 0),
                    'draw' => (int) ($row->draw ?? 0),
                    'lost' => (int) ($row->lost ?? 0),
                    'goal_difference' => (int) ($row->goal_difference ?? 0),
                    'goals_for' => (int) ($row->goals_for ?? 0),
                    'goals_against' => (int) ($row->goals_against ?? 0),
                    'points' => (int) ($row->points ?? 0),
                ])->values()->all(),
            ];
        })->values()->all();

        // Fallback para Copa quando o provedor retorna uma tabela geral:
        // associa cada seleção ao grupo registrado nos jogos, sem dividir a lista em blocos.
        if ($isCup && count($groups) === 1) {
            $single = $groups[0] ?? null;
            $rows = is_array($single['rows'] ?? null) ? $single['rows'] : [];
            $stage = strtoupper((string) ($single['stage'] ?? ''));

            if ($stage === 'GROUP_STAGE' && count($rows) >= 4) {
                $teamGroupMap = [];
                FootballMatch::query()
                    ->where('competition_id', $selectedCompetitionId)
                    ->where('competition_season_id', (int) $seasonId)
                    ->whereNotNull('group_name')
                    ->where('group_name', '!=', '')
                    ->get(['home_team_id', 'away_team_id', 'group_name'])
                    ->each(function (FootballMatch $match) use (&$teamGroupMap): void {
                        if ($match->home_team_id) {
                            $teamGroupMap[(int) $match->home_team_id] = (string) $match->group_name;
                        }
                        if ($match->away_team_id) {
                            $teamGroupMap[(int) $match->away_team_id] = (string) $match->group_name;
                        }
                    });

                $rebuilt = collect($rows)
                    ->groupBy(fn (array $row): string => $teamGroupMap[(int) data_get($row, 'team.id')] ?? 'Classificação Geral')
                    ->map(function ($groupRows, string $groupName): array {
                        return [
                            'name' => StandingRowsSorter::groupLabel($groupName),
                            'stage' => 'GROUP_STAGE',
                            'rows' => $groupRows->values()->map(function (array $row, int $index): array {
                                $row['position'] = $index + 1;

                                return $row;
                            })->all(),
                        ];
                    })
                    ->sortBy('name', SORT_NATURAL)
                    ->values()
                    ->all();

                if (count($rebuilt) > 1) {
                    $groups = $rebuilt;
                }
            }
        }

        return ApiResponse::success($request, [
            'competitions' => $competitions->map(fn (Competition $c): array => [
                'id' => (int) $c->id,
                'code' => (string) $c->code,
                'name' => (string) $c->name,
                'type' => (string) ($c->type ?? ''),
            ])->values()->all(),
            'selected_competition_id' => $selectedCompetitionId,
            'groups' => $groups,
        ]);
    }
}

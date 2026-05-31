<?php

namespace App\Http\Controllers\Api\V1\Standings;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\Standing;
use App\Support\ApiResponse;
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
                    ->orderByRaw('case when position is null then 1 else 0 end')
                    ->orderBy('position')
                    ->orderByDesc('points')
                    ->orderByDesc('goal_difference')
                    ->orderByDesc('goals_for'),
            ])
            ->orderByRaw('case when group_name is null then 1 else 0 end')
            ->orderBy('group_name');

        if ($isCup) {
            $standings = (clone $standingsQuery)
                ->whereNotNull('group_name')
                ->where('group_name', '!=', '')
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

        $groups = $standings->map(function (Standing $standing): array {
            return [
                'name' => $standing->group_name ?: 'Classificação Geral',
                'stage' => (string) ($standing->stage ?? ''),
                'rows' => $standing->rows->map(fn ($row): array => [
                    'position' => $row->position ? (int) $row->position : null,
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

        // Fallback para Copa quando o provedor retorna só uma tabela geral no stage de grupos.
        if ($isCup && count($groups) === 1) {
            $single = $groups[0] ?? null;
            $rows = is_array($single['rows'] ?? null) ? $single['rows'] : [];
            $stage = strtoupper((string) ($single['stage'] ?? ''));

            if ($stage === 'GROUP_STAGE' && count($rows) >= 8 && count($rows) % 4 === 0) {
                $chunks = array_chunk($rows, 4);
                $letters = range('A', 'Z');
                $rebuilt = [];

                foreach ($chunks as $idx => $chunk) {
                    $label = $letters[$idx] ?? (string) ($idx + 1);
                    $rebuiltRows = array_map(function (array $row, int $pos): array {
                        $row['position'] = $pos + 1;
                        return $row;
                    }, array_values($chunk), array_keys($chunk));

                    $rebuilt[] = [
                        'name' => "Grupo {$label}",
                        'stage' => 'GROUP_STAGE',
                        'rows' => $rebuiltRows,
                    ];
                }

                $groups = $rebuilt;
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

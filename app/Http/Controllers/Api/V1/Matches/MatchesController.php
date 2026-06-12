<?php

namespace App\Http\Controllers\Api\V1\Matches;

use App\Http\Controllers\Controller;
use App\Models\FootballMatch;
use App\Support\Api\MatchPayload;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MatchesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'max:40'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'competition_id' => ['nullable', 'integer', 'min:1'],
            'order' => ['nullable', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = FootballMatch::query()
            ->with(['competition:id,code,name', 'homeTeam:id,name,canonical_name_br,short_name,tla,crest', 'awayTeam:id,name,canonical_name_br,short_name,tla,crest']);

        $order = strtolower((string) ($validated['order'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy('utc_date', $order);

        if (! empty($validated['status'])) {
            $status = strtoupper((string) $validated['status']);
            $statusMap = [
                'TIMED' => ['TIMED', 'SCHEDULED', 'PRE_MATCH'],
                'IN_PLAY' => ['IN_PLAY', 'PAUSED', 'HALFTIME', 'EXTRA_TIME', 'PENALTY_SHOOTOUT'],
                'FINISHED' => ['FINISHED', 'AWARDED'],
            ];
            if (isset($statusMap[$status])) {
                $query->whereIn('status', $statusMap[$status]);
            } else {
                $query->where('status', $status);
            }
        }

        if (! empty($validated['competition_id'])) {
            $query->where('competition_id', (int) $validated['competition_id']);
        }

        if (! empty($validated['date_from'])) {
            // Interpreta a data como fuso Brasil e converte para UTC para capturar jogos de 23h BRT corretamente.
            $dateFromUtc = \Carbon\Carbon::parse($validated['date_from'], 'America/Sao_Paulo')->startOfDay()->utc();
            $query->where('utc_date', '>=', $dateFromUtc->format('Y-m-d H:i:s'));
        }

        if (! empty($validated['date_to'])) {
            $dateToUtc = \Carbon\Carbon::parse($validated['date_to'], 'America/Sao_Paulo')->endOfDay()->utc();
            $query->where('utc_date', '<=', $dateToUtc->format('Y-m-d H:i:s'));
        }

        $matches = $query->paginate($perPage)->withQueryString();

        return ApiResponse::success(
            request: $request,
            data: [
                'items' => $matches->getCollection()
                    ->map(fn (FootballMatch $match): array => MatchPayload::fromModel($match))
                    ->values()
                    ->all(),
            ],
            meta: [
                'pagination' => [
                    'page' => $matches->currentPage(),
                    'per_page' => $matches->perPage(),
                    'total' => $matches->total(),
                    'total_pages' => $matches->lastPage(),
                ],
            ],
        );
    }

    public function show(Request $request, FootballMatch $match): JsonResponse
    {
        $match->loadMissing([
            'competition:id,code,name',
            'homeTeam:id,name,canonical_name_br,short_name,tla,crest',
            'awayTeam:id,name,canonical_name_br,short_name,tla,crest',
        ]);

        return ApiResponse::success($request, MatchPayload::fromModel($match));
    }
}

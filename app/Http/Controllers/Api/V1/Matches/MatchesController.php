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
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = FootballMatch::query()
            ->with(['competition:id,code,name', 'homeTeam:id,name,canonical_name_br,short_name,tla,crest', 'awayTeam:id,name,canonical_name_br,short_name,tla,crest'])
            ->orderBy('utc_date');

        if (! empty($validated['status'])) {
            $query->where('status', strtoupper((string) $validated['status']));
        }

        if (! empty($validated['competition_id'])) {
            $query->where('competition_id', (int) $validated['competition_id']);
        }

        if (! empty($validated['date_from'])) {
            $query->where('utc_date', '>=', (string) $validated['date_from'].' 00:00:00');
        }

        if (! empty($validated['date_to'])) {
            $query->where('utc_date', '<=', (string) $validated['date_to'].' 23:59:59');
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

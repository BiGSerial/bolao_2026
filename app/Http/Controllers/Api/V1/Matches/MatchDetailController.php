<?php

namespace App\Http\Controllers\Api\V1\Matches;

use App\Http\Controllers\Controller;
use App\Models\FootballMatch;
use App\Support\Api\MatchDetailPayload;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MatchDetailController extends Controller
{
    public function __invoke(Request $request, FootballMatch $match): JsonResponse
    {
        $match->loadMissing([
            'competition:id,code,name',
            'homeTeam:id,name,canonical_name_br,short_name,tla,crest',
            'awayTeam:id,name,canonical_name_br,short_name,tla,crest',
            'detail',
        ]);

        return ApiResponse::success($request, MatchDetailPayload::fromModel($match));
    }
}

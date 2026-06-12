<?php

namespace App\Http\Controllers\Pools;

use App\Http\Controllers\Controller;
use App\Models\Pool;
use App\Services\Pools\PoolPredictionsPdfData;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PoolPredictionsPdfController extends Controller
{
    public function __construct(
        private readonly PoolPredictionsPdfData $pdfData,
    ) {}

    public function __invoke(Request $request, Pool $pool): Response
    {
        $user = $request->user();
        $isActiveMember = $pool->members()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();

        abort_unless($isActiveMember, 403, 'Apenas membros ativos podem exportar seus palpites.');

        $data = $this->pdfData->build($pool, $user);

        return Pdf::loadView('pdf.pool-predictions', $data)
            ->setPaper('a4', 'portrait')
            ->download($data['filename']);
    }
}

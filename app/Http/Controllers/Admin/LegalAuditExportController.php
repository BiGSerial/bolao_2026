<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LegalAuditExportController extends Controller
{
    public function download(Request $request): BinaryFileResponse
    {
        abort_unless((bool) $request->user()?->is_admin, 403);

        $encodedPath = trim((string) $request->query('path'));
        abort_if($encodedPath === '', 404);

        $decodedPath = base64_decode($encodedPath, true);
        abort_if($decodedPath === false, 404);

        $relativePath = ltrim(str_replace('\\', '/', (string) $decodedPath), '/');

        abort_if(str_contains($relativePath, '../'), 403);
        abort_if(! str_starts_with($relativePath, 'legal-audit-exports/'), 403);
        abort_if(! Storage::disk('local')->exists($relativePath), 404);

        return response()->download(
            Storage::disk('local')->path($relativePath),
            basename($relativePath)
        );
    }
}

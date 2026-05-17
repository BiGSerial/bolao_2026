<?php

namespace App\Services\FootballData;

use App\Models\ApiSyncLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TeamCrestCacheService
{
    public function cache(?string $remoteUrl, ?int $teamExternalId = null): ?string
    {
        if (! $remoteUrl) {
            return null;
        }

        if (str_starts_with($remoteUrl, '/storage/')) {
            return $remoteUrl;
        }

        $path = parse_url($remoteUrl, PHP_URL_PATH);
        $ext = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));

        if ($ext === '' || strlen($ext) > 5) {
            $ext = 'svg';
        }

        $key = $teamExternalId ? (string) $teamExternalId : md5($remoteUrl);
        $filename = 'crests/'.$key.'.'.$ext;

        if (Storage::disk('public')->exists($filename)) {
            return '/storage/'.$filename;
        }

        try {
            $startedAt = now();
            $startedMicro = microtime(true);
            $response = Http::timeout(20)
                ->retry(2, 700)
                ->get($remoteUrl);

            $durationMs = (int) round((microtime(true) - $startedMicro) * 1000);
            $payload = $response->json();
            if (! is_array($payload)) {
                $payload = ['raw' => mb_substr((string) $response->body(), 0, 65000)];
            }
            ApiSyncLog::query()->create([
                'provider' => 'asset_cdn',
                'endpoint' => parse_url($remoteUrl, PHP_URL_PATH) ?: '/',
                'http_status' => $response->status(),
                'success' => $response->successful(),
                'is_request_log' => true,
                'request_method' => 'GET',
                'request_url' => $remoteUrl,
                'request_query' => [],
                'response_payload' => $payload,
                'request_started_at' => $startedAt,
                'request_finished_at' => now(),
                'duration_ms' => $durationMs,
                'message' => 'Download de escudo registrado.',
                'meta' => [
                    'request_level' => true,
                    'duration_ms' => $durationMs,
                ],
                'synced_at' => now(),
            ]);

            if (! $response->successful() || $response->body() === '') {
                return $remoteUrl;
            }

            Storage::disk('public')->put($filename, $response->body());

            return '/storage/'.$filename;
        } catch (Throwable $e) {
            try {
                ApiSyncLog::query()->create([
                    'provider' => 'asset_cdn',
                    'endpoint' => parse_url($remoteUrl, PHP_URL_PATH) ?: '/',
                    'http_status' => null,
                    'success' => false,
                    'is_request_log' => true,
                    'request_method' => 'GET',
                    'request_url' => $remoteUrl,
                    'request_query' => [],
                    'response_payload' => null,
                    'request_started_at' => now(),
                    'request_finished_at' => now(),
                    'duration_ms' => null,
                    'message' => $e->getMessage(),
                    'meta' => [
                        'request_level' => true,
                        'error' => $e->getMessage(),
                    ],
                    'synced_at' => now(),
                ]);
            } catch (Throwable) {
            }
            return $remoteUrl;
        }
    }
}

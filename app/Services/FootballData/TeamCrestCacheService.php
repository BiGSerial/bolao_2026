<?php

namespace App\Services\FootballData;

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
            $response = Http::timeout(20)
                ->retry(2, 700)
                ->get($remoteUrl);

            if (! $response->successful() || $response->body() === '') {
                return $remoteUrl;
            }

            Storage::disk('public')->put($filename, $response->body());

            return '/storage/'.$filename;
        } catch (Throwable) {
            return $remoteUrl;
        }
    }
}

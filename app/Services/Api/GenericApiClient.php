<?php

namespace App\Services\Api;

use App\DTO\ApiRequestData;
use App\DTO\ApiResponseData;
use App\Models\ApiSyncLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;
use RuntimeException;
use Throwable;

class GenericApiClient
{
    public function __construct(private readonly array $config)
    {
    }

    public function get(string $endpoint, array $query = [], array $headers = []): ApiResponseData
    {
        return $this->send(ApiRequestData::get($endpoint, $query, $headers));
    }

    public function send(ApiRequestData $request): ApiResponseData
    {
        $cacheEnabled = (bool) data_get($this->config, 'response_cache.enabled', true);
        $cacheKey = $this->responseCacheKey($request->endpoint, $request->query);
        $ttl = $this->responseCacheTtl($request->endpoint);

        if ($cacheEnabled) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return new ApiResponseData(200, [], $cached, true);
            }
        }

        $requestLock = Cache::lock($cacheKey.':lock', 20);
        $requestLock->block(10);

        try {
            if ($cacheEnabled) {
                $cached = Cache::get($cacheKey);
                if (is_array($cached)) {
                    return new ApiResponseData(200, [], $cached, true);
                }
            }

            $response = $this->requestFromProvider($request);

            if ($cacheEnabled && $ttl > 0) {
                Cache::put($cacheKey, $response->json(), now()->addSeconds($ttl));
            }

            return $response;
        } finally {
            optional($requestLock)->release();
        }
    }

    private function requestFromProvider(ApiRequestData $request): ApiResponseData
    {
        $startedAt = microtime(true);
        $startedCarbon = now();
        $provider = (string) data_get($this->config, 'provider', 'external_api');
        $baseUrl = (string) data_get($this->config, 'base_url', '');
        $fullUrl = rtrim($baseUrl, '/').'/'.ltrim($request->endpoint, '/');

        $this->acquireRateLimitSlot();

        if ($baseUrl === '') {
            throw new RuntimeException('Base URL da API nao configurada.');
        }

        $token = (string) data_get($this->config, 'token', '');
        $tokenHeader = (string) data_get($this->config, 'auth.token_header', '');
        if ($tokenHeader !== '' && $token === '') {
            throw new RuntimeException('Token da API nao configurado.');
        }

        $defaultHeaders = (array) data_get($this->config, 'default_headers', []);
        if ($tokenHeader !== '' && $token !== '') {
            $defaultHeaders[$tokenHeader] = $token;
        }

        $timeout = max(1, (int) data_get($this->config, 'http.timeout', 20));
        $retryTimes = max(0, (int) data_get($this->config, 'http.retry.times', 2));
        $retrySleepMs = max(0, (int) data_get($this->config, 'http.retry.sleep_ms', 1000));

        try {
            $response = Http::baseUrl($baseUrl)
                ->withHeaders(array_merge($defaultHeaders, $request->headers))
                ->timeout($timeout)
                ->retry($retryTimes, $retrySleepMs)
                ->send($request->method, $request->endpoint, [
                    'query' => $request->query,
                    'json' => $request->body,
                ]);

            $status = (int) $response->status();
            $payload = $response->json();

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $finishedAt = now();
            $this->logRequest(
                provider: $provider,
                endpoint: $request->endpoint,
                method: $request->method,
                url: $fullUrl,
                query: $request->query,
                status: $status,
                success: $status >= 200 && $status < 300,
                payload: $this->normalizePayloadForJson($payload, $response->body()),
                startedAt: $startedCarbon,
                finishedAt: $finishedAt,
                durationMs: $durationMs,
            );

            if ($status === 429) {
                throw new RuntimeException('Rate limit do provedor atingido.');
            }

            $response->throw();

            return new ApiResponseData($status, $response->headers(), is_array($payload) ? $payload : [], false);
        } catch (Throwable $e) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $finishedAt = now();
            $status = $e instanceof RequestException ? (int) ($e->response?->status() ?? 0) : 0;
            $rawBody = $e instanceof RequestException ? (string) ($e->response?->body() ?? '') : '';
            $jsonBody = $e instanceof RequestException ? $e->response?->json() : null;

            $this->logRequest(
                provider: $provider,
                endpoint: $request->endpoint,
                method: $request->method,
                url: $fullUrl,
                query: $request->query,
                status: $status > 0 ? $status : null,
                success: false,
                payload: $this->normalizePayloadForJson($jsonBody, $rawBody),
                startedAt: $startedCarbon,
                finishedAt: $finishedAt,
                durationMs: $durationMs,
                error: $e->getMessage(),
            );

            throw $e;
        }
    }

    /**
     * @param array<string,mixed> $query
     * @param mixed $payload
     */
    private function logRequest(
        string $provider,
        string $endpoint,
        string $method,
        string $url,
        array $query,
        ?int $status,
        bool $success,
        mixed $payload,
        Carbon $startedAt,
        Carbon $finishedAt,
        int $durationMs,
        ?string $error = null
    ): void {
        try {
            ApiSyncLog::query()->create([
                'provider' => $provider,
                'endpoint' => $endpoint,
                'http_status' => $status,
                'success' => $success,
                'is_request_log' => true,
                'request_method' => strtoupper($method),
                'request_url' => $url,
                'request_query' => $query,
                'response_payload' => $payload,
                'request_started_at' => $startedAt,
                'request_finished_at' => $finishedAt,
                'duration_ms' => max(0, $durationMs),
                'message' => $error ?: 'Requisicao HTTP registrada.',
                'meta' => [
                    'status' => $success ? 'success' : 'failed',
                    'request_level' => true,
                    'started_at_iso8601' => $startedAt->toIso8601String(),
                    'finished_at_iso8601' => $finishedAt->toIso8601String(),
                    'duration_ms' => max(0, $durationMs),
                    'duration_seconds' => round(max(0, $durationMs) / 1000, 3),
                    'error' => $error,
                ],
                'synced_at' => $finishedAt,
            ]);
        } catch (Throwable) {
            // Não interrompe fluxo principal por falha de auditoria.
        }
    }

    private function normalizePayloadForJson(mixed $jsonPayload, string $rawBody): mixed
    {
        if (is_array($jsonPayload)) {
            return $jsonPayload;
        }

        if (is_object($jsonPayload)) {
            return (array) $jsonPayload;
        }

        $trimmed = trim($rawBody);
        if ($trimmed === '') {
            return null;
        }

        return ['raw' => mb_substr($trimmed, 0, 65000)];
    }

    private function responseCacheKey(string $endpoint, array $query): string
    {
        ksort($query);
        $prefix = (string) data_get($this->config, 'response_cache.key_prefix', 'api:response:');

        return $prefix.sha1($endpoint.'|'.http_build_query($query));
    }

    private function responseCacheTtl(string $endpoint): int
    {
        $default = max(0, (int) data_get($this->config, 'response_cache.default_ttl_seconds', 30));
        $endpointTtls = (array) data_get($this->config, 'response_cache.ttl_by_endpoint', []);

        foreach ($endpointTtls as $pattern => $ttl) {
            if (preg_match($pattern, $endpoint) === 1) {
                return max(0, (int) $ttl);
            }
        }

        return $default;
    }

    private function acquireRateLimitSlot(): void
    {
        $limit = max(1, (int) data_get($this->config, 'rate_limit.requests_per_minute',
            data_get($this->config, 'rate_limit.free_requests_per_minute', 10)));
        $lockName = (string) data_get($this->config, 'rate_limit.lock_key', 'api:rate-limit:lock');
        $counterPrefix = (string) data_get($this->config, 'rate_limit.counter_key_prefix', 'api:rate-limit:counter:');
        $maxWaitSeconds = max(10, (int) data_get($this->config, 'rate_limit.max_wait_seconds', 120));
        $startedAt = microtime(true);

        while (true) {
            $minuteKey = now()->utc()->format('YmdHi');
            $counterKey = $counterPrefix.$minuteKey;

            $lock = Cache::lock($lockName, 5);
            $acquired = $lock->block(5);

            if (! $acquired) {
                usleep(200000);
                continue;
            }

            try {
                $count = (int) Cache::get($counterKey, 0);

                if ($count < $limit) {
                    Cache::put($counterKey, $count + 1, now()->addMinutes(2));

                    return;
                }
            } finally {
                optional($lock)->release();
            }

            if ((microtime(true) - $startedAt) >= $maxWaitSeconds) {
                throw new RuntimeException('Timeout aguardando slot do rate-limit da API.');
            }

            $secondsToNextMinute = 60 - ((int) now()->utc()->format('s'));
            $sleepSeconds = max(1, min($secondsToNextMinute, 5));
            sleep($sleepSeconds);
        }
    }
}

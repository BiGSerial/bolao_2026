<?php

namespace App\Services\Api;

use App\DTO\ApiRequestData;
use App\DTO\ApiResponseData;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

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
        $this->acquireRateLimitSlot();

        $baseUrl = (string) data_get($this->config, 'base_url', '');
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

        $response = Http::baseUrl($baseUrl)
            ->withHeaders(array_merge($defaultHeaders, $request->headers))
            ->timeout($timeout)
            ->retry($retryTimes, $retrySleepMs)
            ->send($request->method, $request->endpoint, [
                'query' => $request->query,
                'json' => $request->body,
            ]);

        if ($response->status() === 429) {
            throw new RuntimeException('Rate limit do provedor atingido.');
        }

        $response->throw();

        return new ApiResponseData($response->status(), $response->headers(), $response->json(), false);
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

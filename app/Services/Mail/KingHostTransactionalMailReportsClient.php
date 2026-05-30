<?php

namespace App\Services\Mail;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class KingHostTransactionalMailReportsClient
{
    /**
     * @return array<int, array<string,mixed>>
     */
    public function report(string $endpoint, string $startDate, string $endDate): array
    {
        $payload = $this->get($endpoint, [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $data = Arr::get($payload, 'data', []);

        return is_array($data) ? array_values($data) : [];
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function messages(string $status, string $startDate, string $endDate): array
    {
        $items = [];
        $page = 1;

        do {
            $payload = $this->get('messages', [
                'status' => $status,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'page' => $page,
            ]);

            $batch = Arr::get($payload, 'data.messages', []);
            if (is_array($batch)) {
                foreach ($batch as $row) {
                    if (is_array($row)) {
                        $items[] = $row;
                    }
                }
            }

            $next = Arr::get($payload, 'links.next');
            $hasNext = is_string($next) && trim($next) !== '';
            $page++;
        } while ($hasNext);

        return $items;
    }

    /**
     * @return array<string,mixed>
     */
    private function get(string $endpoint, array $query): array
    {
        $cfg = (array) config('services.kinghost_smtp', []);
        $token = trim((string) ($cfg['token'] ?? ''));
        if ($token === '') {
            throw new RuntimeException('KINGHOST_SMTP_API_TOKEN não configurado.');
        }

        $baseUrl = rtrim((string) ($cfg['base_url'] ?? 'https://api.smtplw.com.br/v1'), '/');
        $timeout = (int) ($cfg['timeout'] ?? 15);
        $retryTimes = (int) ($cfg['retry_times'] ?? 3);
        $retrySleep = (int) ($cfg['retry_sleep'] ?? 500);

        $response = Http::acceptJson()
            ->timeout($timeout)
            ->retry($retryTimes, $retrySleep, throw: false)
            ->withHeaders(['x-auth-token' => $token])
            ->get($baseUrl.'/'.ltrim($endpoint, '/'), $query);

        if (! $response->successful()) {
            $body = $response->json();
            $detail = is_array($body) ? json_encode($body, JSON_UNESCAPED_UNICODE) : $response->body();
            throw new RuntimeException(sprintf('KingHost reports API HTTP %d em %s: %s', $response->status(), $endpoint, (string) $detail));
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }
}

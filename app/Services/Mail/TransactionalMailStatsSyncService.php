<?php

namespace App\Services\Mail;

use App\Models\TransactionalMailLog;
use App\Models\TransactionalMailMetricSnapshot;
use Illuminate\Support\Carbon;

class TransactionalMailStatsSyncService
{
    public function __construct(
        private readonly KingHostTransactionalMailReportsClient $client,
    ) {
    }

    /**
     * @return array{start_date:string,end_date:string,snapshots_upserted:int,messages_upserted:int}
     */
    public function syncRange(?Carbon $start = null, ?Carbon $end = null, string $provider = 'kinghost_smtp'): array
    {
        $startDate = ($start ?: now()->startOfMonth())->toDateString();
        $endDate = ($end ?: now())->toDateString();

        $sentRows = $this->client->report('reports/sent', $startDate, $endDate);
        $balanceRows = $this->client->report('reports/balance', $startDate, $endDate);
        $messageRows = $this->client->messages('all', $startDate, $endDate);

        $upserts = 0;
        $messageUpserts = 0;
        $syncedAt = now();

        foreach ($sentRows as $row) {
            $day = (string) ($row['day'] ?? '');
            if ($day === '') {
                continue;
            }

            TransactionalMailMetricSnapshot::query()->updateOrCreate(
                [
                    'provider' => $provider,
                    'period_type' => 'daily',
                    'reference_date' => $day,
                ],
                [
                    'messages' => (int) ($row['messages'] ?? 0),
                    'bounces' => (int) ($row['bounces'] ?? 0),
                    'hard_bounces' => (int) ($row['hard_bounces'] ?? 0),
                    'openings' => (int) ($row['openings'] ?? 0),
                    'payload' => $row,
                    'synced_at' => $syncedAt,
                ]
            );
            $upserts++;
        }

        foreach ($balanceRows as $row) {
            $periodEnd = (string) ($row['period_end'] ?? now()->toDateString());

            TransactionalMailMetricSnapshot::query()->updateOrCreate(
                [
                    'provider' => $provider,
                    'period_type' => 'monthly_balance',
                    'reference_date' => $periodEnd,
                ],
                [
                    'total_hired' => $this->nullableInt($row['total_hired'] ?? null),
                    'total_excess_hired' => $this->nullableInt($row['total_excess_hired'] ?? null),
                    'total_consumed' => $this->nullableInt($row['total_consumed'] ?? null),
                    'total_exceeded' => $this->nullableInt($row['total_exceeded'] ?? null),
                    'total_available' => $this->nullableInt($row['total_available'] ?? null),
                    'payload' => $row,
                    'synced_at' => $syncedAt,
                ]
            );
            $upserts++;
        }

        foreach ($messageRows as $row) {
            $externalId = $this->resolveExternalId($row);
            if ($externalId === '') {
                continue;
            }

            $normalizedStatus = $this->normalizeMessageStatus((string) ($row['status'] ?? ''));
            $sentAt = $this->parseNullableDate(data_get($row, 'sent_at'));
            $apiCreatedAt = $this->parseNullableDate(data_get($row, 'created_at'));
            $failedAt = $normalizedStatus === 'failed' ? ($sentAt ?: $apiCreatedAt ?: $syncedAt) : null;

            TransactionalMailLog::query()->updateOrCreate(
                [
                    'provider' => $provider,
                    'external_id' => $externalId,
                ],
                [
                    'from_address' => (string) ($row['sender'] ?? 'desconhecido@smtplw.local'),
                    'to_address' => (string) ($row['recipient'] ?? ''),
                    'subject' => (string) ($row['subject'] ?? '(sem assunto)'),
                    'status' => $normalizedStatus,
                    'payload' => $row,
                    'response' => ['source' => 'kinghost_messages_api', 'synced_at' => $syncedAt->toIso8601String()],
                    'queued_at' => $apiCreatedAt,
                    'sending_at' => $apiCreatedAt,
                    'sent_at' => $sentAt,
                    'failed_at' => $failedAt,
                    'updated_at' => $syncedAt,
                    'created_at' => $apiCreatedAt ?: $syncedAt,
                ]
            );
            $messageUpserts++;
        }

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'snapshots_upserted' => $upserts,
            'messages_upserted' => $messageUpserts,
        ];
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function resolveExternalId(array $row): string
    {
        $id = $row['id'] ?? null;
        if (is_scalar($id) && trim((string) $id) !== '') {
            return (string) $id;
        }

        $uid = $row['uid'] ?? null;
        if (is_scalar($uid) && trim((string) $uid) !== '') {
            return (string) $uid;
        }

        return '';
    }

    private function normalizeMessageStatus(string $apiStatus): string
    {
        $status = mb_strtolower(trim($apiStatus));

        return match ($status) {
            'entregue', 'delivered' => 'sent',
            'erro', 'error', 'errors', 'falhou', 'failed' => 'failed',
            default => 'queued',
        };
    }

    private function parseNullableDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}

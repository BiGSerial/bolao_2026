<?php

namespace App\Services\Legal;

use App\Models\UserLegalAcceptance;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LegalAuditExportService
{
    /**
     * @param array{user_id?:int,document_id?:int,document_type?:string,from?:CarbonImmutable,to?:CarbonImmutable} $filters
     * @return array<string,mixed>
     */
    public function export(array $filters = [], bool $withSnapshot = false, ?string $outputDir = null): array
    {
        $baseOutputDir = trim((string) ($outputDir ?? 'legal-audit-exports')) ?: 'legal-audit-exports';
        $runId = now()->format('Ymd_His').'_'.Str::lower(Str::random(8));
        $exportDir = trim($baseOutputDir, '/').'/'.$runId;

        $query = UserLegalAcceptance::query()
            ->with(['user:id,name,email', 'legalDocument:id,type,title,version,content_hash,content'])
            ->orderBy('accepted_at')
            ->orderBy('id');

        $this->applyFilters($query, $filters);

        $records = $query->get();
        if ($records->isEmpty()) {
            return [
                'total_records' => 0,
                'run_id' => $runId,
            ];
        }

        Storage::disk('local')->makeDirectory($exportDir);

        $csvFile = $exportDir.'/legal_audit_'.$runId.'.csv';
        $jsonFile = $exportDir.'/legal_audit_'.$runId.'.json';
        $manifestFile = $exportDir.'/legal_audit_'.$runId.'.manifest.json';

        $jsonPayload = $this->buildJsonPayload($records, $withSnapshot, $filters);

        $this->writeCsv($csvFile, $jsonPayload['records']);
        Storage::disk('local')->put($jsonFile, json_encode($jsonPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $manifest = $this->buildManifest($csvFile, $jsonFile, $jsonPayload['meta']);
        Storage::disk('local')->put($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return [
            'total_records' => count($jsonPayload['records']),
            'run_id' => $runId,
            'export_dir' => $exportDir,
            'csv_path' => $csvFile,
            'json_path' => $jsonFile,
            'manifest_path' => $manifestFile,
            'manifest' => $manifest,
            'meta' => $jsonPayload['meta'],
        ];
    }

    /**
     * @param array{user_id?:int,document_id?:int,document_type?:string,from?:CarbonImmutable,to?:CarbonImmutable} $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (isset($filters['user_id']) && (int) $filters['user_id'] > 0) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (isset($filters['document_id']) && (int) $filters['document_id'] > 0) {
            $query->where('legal_document_id', (int) $filters['document_id']);
        }

        $docType = strtoupper(trim((string) ($filters['document_type'] ?? '')));
        if ($docType !== '') {
            $query->whereHas('legalDocument', fn (Builder $docQ) => $docQ->where('type', $docType));
        }

        if (isset($filters['from']) && $filters['from'] instanceof CarbonImmutable) {
            $query->where('accepted_at', '>=', $filters['from']->toDateTimeString());
        }

        if (isset($filters['to']) && $filters['to'] instanceof CarbonImmutable) {
            $query->where('accepted_at', '<=', $filters['to']->toDateTimeString());
        }
    }

    /**
     * @param Collection<int, UserLegalAcceptance> $records
     * @param array{user_id?:int,document_id?:int,document_type?:string,from?:CarbonImmutable,to?:CarbonImmutable} $filters
     * @return array{meta:array<string,mixed>,records:array<int,array<string,mixed>>}
     */
    private function buildJsonPayload(Collection $records, bool $withSnapshot, array $filters): array
    {
        $rows = $records->map(function (UserLegalAcceptance $acceptance) use ($withSnapshot): array {
            $document = $acceptance->legalDocument;
            $user = $acceptance->user;

            $snapshot = (string) ($acceptance->accepted_document_snapshot ?? '');
            $snapshotHash = hash('sha256', $snapshot);
            $currentDocumentHash = (string) ($document?->content_hash ?? '');
            $acceptedHash = (string) ($acceptance->accepted_document_hash ?? '');

            $row = [
                'acceptance_id' => $acceptance->id,
                'user' => [
                    'id' => $acceptance->user_id,
                    'name' => $user?->name,
                    'email' => $user?->email,
                ],
                'document' => [
                    'id' => $acceptance->legal_document_id,
                    'type' => $document?->type?->value ?? $document?->type,
                    'title' => $document?->title,
                    'accepted_version' => $acceptance->accepted_document_version,
                    'accepted_hash' => $acceptedHash,
                    'current_hash' => $currentDocumentHash,
                    'hash_matches_current' => $acceptedHash !== '' && $currentDocumentHash !== ''
                        ? hash_equals($acceptedHash, $currentDocumentHash)
                        : null,
                    'snapshot_hash_recalc' => $snapshotHash,
                    'snapshot_hash_matches_stored' => $acceptedHash !== '' ? hash_equals($acceptedHash, $snapshotHash) : null,
                ],
                'acceptance' => [
                    'accepted_at' => optional($acceptance->accepted_at)?->toIso8601String(),
                    'method' => $acceptance->acceptance_method,
                    'context' => $acceptance->acceptance_context,
                    'ip_hash' => $acceptance->ip_hash,
                    'user_agent_hash' => $acceptance->user_agent_hash,
                ],
                'recorded_at' => optional($acceptance->created_at)?->toIso8601String(),
            ];

            if ($withSnapshot) {
                $row['document']['snapshot'] = $snapshot;
            }

            return $row;
        })->values()->all();

        return [
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'app' => config('app.name'),
                'total_records' => count($rows),
                'filters' => [
                    'user_id' => $filters['user_id'] ?? null,
                    'document_id' => $filters['document_id'] ?? null,
                    'document_type' => $filters['document_type'] ?? null,
                    'from' => isset($filters['from']) && $filters['from'] instanceof CarbonImmutable ? $filters['from']->toIso8601String() : null,
                    'to' => isset($filters['to']) && $filters['to'] instanceof CarbonImmutable ? $filters['to']->toIso8601String() : null,
                    'with_snapshot' => $withSnapshot,
                ],
            ],
            'records' => $rows,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function writeCsv(string $relativePath, array $rows): void
    {
        $handle = fopen('php://temp', 'wb+');

        if (! is_resource($handle)) {
            throw new \RuntimeException('Nao foi possivel criar arquivo CSV de auditoria.');
        }

        $headers = [
            'acceptance_id',
            'user_id',
            'user_name',
            'user_email',
            'document_id',
            'document_type',
            'document_title',
            'accepted_version',
            'accepted_hash',
            'current_hash',
            'hash_matches_current',
            'snapshot_hash_recalc',
            'snapshot_hash_matches_stored',
            'accepted_at',
            'acceptance_method',
            'acceptance_route',
            'acceptance_path',
            'ip_hash',
            'user_agent_hash',
            'recorded_at',
        ];

        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            $context = (array) data_get($row, 'acceptance.context', []);

            fputcsv($handle, [
                data_get($row, 'acceptance_id'),
                data_get($row, 'user.id'),
                data_get($row, 'user.name'),
                data_get($row, 'user.email'),
                data_get($row, 'document.id'),
                data_get($row, 'document.type'),
                data_get($row, 'document.title'),
                data_get($row, 'document.accepted_version'),
                data_get($row, 'document.accepted_hash'),
                data_get($row, 'document.current_hash'),
                $this->toCsvBool(data_get($row, 'document.hash_matches_current')),
                data_get($row, 'document.snapshot_hash_recalc'),
                $this->toCsvBool(data_get($row, 'document.snapshot_hash_matches_stored')),
                data_get($row, 'acceptance.accepted_at'),
                data_get($row, 'acceptance.method'),
                $context['route'] ?? null,
                $context['path'] ?? null,
                data_get($row, 'acceptance.ip_hash'),
                data_get($row, 'acceptance.user_agent_hash'),
                data_get($row, 'recorded_at'),
            ]);
        }

        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);

        if (! is_string($csvContent)) {
            throw new \RuntimeException('Nao foi possivel gerar conteudo CSV de auditoria.');
        }

        Storage::disk('local')->put($relativePath, $csvContent);
    }

    /**
     * @param array<string,mixed> $meta
     * @return array<string,mixed>
     */
    private function buildManifest(string $csvPath, string $jsonPath, array $meta): array
    {
        $csvContent = Storage::disk('local')->get($csvPath);
        $jsonContent = Storage::disk('local')->get($jsonPath);

        return [
            'generated_at' => now()->toIso8601String(),
            'meta' => $meta,
            'files' => [
                [
                    'path' => $csvPath,
                    'size' => strlen($csvContent),
                    'sha256' => hash('sha256', $csvContent),
                ],
                [
                    'path' => $jsonPath,
                    'size' => strlen($jsonContent),
                    'sha256' => hash('sha256', $jsonContent),
                ],
            ],
        ];
    }

    private function toCsvBool(mixed $value): ?string
    {
        if (! is_bool($value)) {
            return null;
        }

        return $value ? 'true' : 'false';
    }
}

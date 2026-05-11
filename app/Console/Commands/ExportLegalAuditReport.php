<?php

namespace App\Console\Commands;

use App\Services\Legal\LegalAuditExportService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class ExportLegalAuditReport extends Command
{
    protected $signature = 'legal:export-audit
        {--user-id= : Filtra por usuario}
        {--document-id= : Filtra por documento juridico}
        {--document-type= : Filtra por tipo (EULA, PRIVACY_POLICY, DISCLAIMER, CONFIDENTIALITY_POLICY)}
        {--from= : Data inicial ISO-8601 (campo accepted_at)}
        {--to= : Data final ISO-8601 (campo accepted_at)}
        {--with-snapshot : Inclui snapshot completo do documento no JSON}
        {--output= : Diretorio de saida relativo ao disk local (padrao: legal-audit-exports)}';

    protected $description = 'Exporta relatorio forense de aceites juridicos em CSV + JSON + manifest com checksum SHA-256.';

    public function handle(LegalAuditExportService $service): int
    {
        $baseOutputDir = trim((string) $this->option('output')) ?: 'legal-audit-exports';
        try {
            $from = $this->parseDateOption('from');
            $to = $this->parseDateOption('to');
        } catch (\RuntimeException) {
            return self::FAILURE;
        }

        if ($from && $to && $to->lt($from)) {
            $this->error('Opcao --to deve ser maior ou igual a --from.');

            return self::FAILURE;
        }

        $result = $service->export(
            filters: [
                'user_id' => $this->option('user-id') !== null && $this->option('user-id') !== '' ? (int) $this->option('user-id') : null,
                'document_id' => $this->option('document-id') !== null && $this->option('document-id') !== '' ? (int) $this->option('document-id') : null,
                'document_type' => $this->option('document-type') !== null && $this->option('document-type') !== '' ? (string) $this->option('document-type') : null,
                'from' => $from,
                'to' => $to,
            ],
            withSnapshot: (bool) $this->option('with-snapshot'),
            outputDir: $baseOutputDir,
        );

        if (($result['total_records'] ?? 0) === 0) {
            $this->warn('Nenhum aceite juridico encontrado para os filtros informados.');

            return self::SUCCESS;
        }

        $this->info('Exportacao forense concluida com sucesso.');
        $this->line('Diretorio: '.storage_path('app/'.(string) $result['export_dir']));
        $this->line('CSV: '.(string) $result['csv_path']);
        $this->line('JSON: '.(string) $result['json_path']);
        $this->line('Manifest: '.(string) $result['manifest_path']);
        $this->newLine();
        $this->line('Checksums SHA-256:');
        $this->line('  '.data_get($result, 'manifest.files.0.path').' => '.data_get($result, 'manifest.files.0.sha256'));
        $this->line('  '.data_get($result, 'manifest.files.1.path').' => '.data_get($result, 'manifest.files.1.sha256'));

        return self::SUCCESS;
    }

    private function parseDateOption(string $key): ?CarbonImmutable
    {
        $value = trim((string) $this->option($key));
        if ($value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            $this->error("Data invalida para --{$key}: {$value}");
            throw new \RuntimeException('Invalid date option.');
        }
    }
}

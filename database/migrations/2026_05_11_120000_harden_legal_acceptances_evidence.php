<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('legal_documents')) {
            Schema::table('legal_documents', function (Blueprint $table): void {
                if (! Schema::hasColumn('legal_documents', 'slug')) {
                    $table->string('slug')->nullable()->after('title');
                }

                if (! Schema::hasColumn('legal_documents', 'content_hash')) {
                    $table->char('content_hash', 64)->nullable()->after('content');
                }
            });

            Schema::table('legal_documents', function (Blueprint $table): void {
                try {
                    $table->unique('slug');
                } catch (\Throwable) {
                    // Index already exists.
                }

                try {
                    $table->index('content_hash');
                } catch (\Throwable) {
                    // Index already exists.
                }
            });

            DB::table('legal_documents')
                ->orderBy('id')
                ->select(['id', 'type', 'version', 'content', 'slug'])
                ->chunkById(200, function ($rows): void {
                    foreach ($rows as $row) {
                        $hash = hash('sha256', (string) ($row->content ?? ''));
                        $baseSlug = Str::slug((string) $row->type.'-'.(string) $row->version);
                        $slug = $baseSlug !== '' ? $baseSlug : 'legal-document-'.$row->id;

                        $collision = DB::table('legal_documents')
                            ->where('slug', $slug)
                            ->where('id', '!=', $row->id)
                            ->exists();

                        if ($collision) {
                            $slug .= '-'.$row->id;
                        }

                        DB::table('legal_documents')
                            ->where('id', $row->id)
                            ->update([
                                'content_hash' => $hash,
                                'slug' => $row->slug ?: $slug,
                            ]);
                    }
                });
        }

        if (Schema::hasTable('user_legal_acceptances')) {
            Schema::table('user_legal_acceptances', function (Blueprint $table): void {
                if (! Schema::hasColumn('user_legal_acceptances', 'acceptance_method')) {
                    $table->string('acceptance_method', 40)->nullable()->after('accepted_at');
                }

                if (! Schema::hasColumn('user_legal_acceptances', 'accepted_document_version')) {
                    $table->string('accepted_document_version', 20)->nullable()->after('acceptance_method');
                }

                if (! Schema::hasColumn('user_legal_acceptances', 'accepted_document_hash')) {
                    $table->char('accepted_document_hash', 64)->nullable()->after('accepted_document_version');
                }

                if (! Schema::hasColumn('user_legal_acceptances', 'accepted_document_snapshot')) {
                    $table->longText('accepted_document_snapshot')->nullable()->after('accepted_document_hash');
                }

                if (! Schema::hasColumn('user_legal_acceptances', 'ip_hash')) {
                    $table->char('ip_hash', 64)->nullable()->after('ip_address');
                }

                if (! Schema::hasColumn('user_legal_acceptances', 'user_agent_hash')) {
                    $table->char('user_agent_hash', 64)->nullable()->after('user_agent');
                }

                if (! Schema::hasColumn('user_legal_acceptances', 'acceptance_context')) {
                    $table->json('acceptance_context')->nullable()->after('user_agent_hash');
                }
            });

            Schema::table('user_legal_acceptances', function (Blueprint $table): void {
                try {
                    $table->index('acceptance_method');
                } catch (\Throwable) {
                    // Index already exists.
                }

                try {
                    $table->index('accepted_document_hash');
                } catch (\Throwable) {
                    // Index already exists.
                }
            });

            $pepper = (string) config('app.key', '');

            DB::table('user_legal_acceptances as ula')
                ->join('legal_documents as ld', 'ld.id', '=', 'ula.legal_document_id')
                ->orderBy('ula.id')
                ->select([
                    'ula.id',
                    'ula.ip_address',
                    'ula.user_agent',
                    'ula.acceptance_method',
                    'ld.version',
                    'ld.content',
                    'ld.content_hash',
                ])
                ->chunkById(200, function ($rows) use ($pepper): void {
                    foreach ($rows as $row) {
                        $ip = (string) ($row->ip_address ?? '');
                        $ua = (string) ($row->user_agent ?? '');

                        $contentHash = (string) ($row->content_hash ?: hash('sha256', (string) ($row->content ?? '')));

                        DB::table('user_legal_acceptances')
                            ->where('id', $row->id)
                            ->update([
                                'acceptance_method' => $row->acceptance_method ?: 'legacy_web',
                                'accepted_document_version' => (string) ($row->version ?? ''),
                                'accepted_document_hash' => $contentHash,
                                'accepted_document_snapshot' => (string) ($row->content ?? ''),
                                'ip_hash' => $ip !== '' ? hash_hmac('sha256', $ip, $pepper) : null,
                                'user_agent_hash' => $ua !== '' ? hash_hmac('sha256', $ua, $pepper) : null,
                                'acceptance_context' => json_encode([
                                    'source' => 'migration_backfill',
                                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            ]);
                    }
                }, 'ula.id', 'id');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user_legal_acceptances')) {
            Schema::table('user_legal_acceptances', function (Blueprint $table): void {
                try {
                    $table->dropIndex('user_legal_acceptances_acceptance_method_index');
                } catch (\Throwable) {
                    // Ignore when index does not exist.
                }

                try {
                    $table->dropIndex('user_legal_acceptances_accepted_document_hash_index');
                } catch (\Throwable) {
                    // Ignore when index does not exist.
                }

                foreach ([
                    'acceptance_method',
                    'accepted_document_version',
                    'accepted_document_hash',
                    'accepted_document_snapshot',
                    'ip_hash',
                    'user_agent_hash',
                    'acceptance_context',
                ] as $column) {
                    if (Schema::hasColumn('user_legal_acceptances', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('legal_documents')) {
            Schema::table('legal_documents', function (Blueprint $table): void {
                try {
                    $table->dropUnique('legal_documents_slug_unique');
                } catch (\Throwable) {
                    // Ignore when index does not exist.
                }

                try {
                    $table->dropIndex('legal_documents_content_hash_index');
                } catch (\Throwable) {
                    // Ignore when index does not exist.
                }

                foreach (['slug', 'content_hash'] as $column) {
                    if (Schema::hasColumn('legal_documents', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

};

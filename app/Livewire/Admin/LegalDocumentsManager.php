<?php

namespace App\Livewire\Admin;

use App\Enums\LegalDocumentType;
use App\Models\LegalDocument;
use App\Models\UserLegalAcceptance;
use App\Services\Legal\LegalDocumentPublishingService;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class LegalDocumentsManager extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterType = '';

    public string $type = LegalDocumentType::Eula->value;
    public string $title = '';
    public string $content = '';
    public bool $publishNow = false;

    public string $acceptanceSearch = '';

    public function mount(): void
    {
        $this->assertAdmin();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterType(): void
    {
        $this->resetPage();
    }

    public function updatingAcceptanceSearch(): void
    {
        $this->resetPage('acc_page');
    }

    public function save(): void
    {
        $this->assertAdmin();

        $validated = $this->validate([
            'type'       => ['required', 'in:EULA,PRIVACY_POLICY'],
            'title'      => ['required', 'string', 'min:5', 'max:255'],
            'content'    => ['required', 'string', 'min:20'],
            'publishNow' => ['boolean'],
        ]);

        try {
            $autoVersion = $this->nextVersionForType($validated['type']);

            $resolvedContent = $this->resolveVariables(trim($validated['content']), $autoVersion);

            $document = LegalDocument::create([
                'type'       => $validated['type'],
                'title'      => trim($validated['title']),
                'version'    => $autoVersion,
                'content'    => $resolvedContent,
                'is_active'  => false,
                'created_by' => Auth::id(),
            ]);

            if ($validated['publishNow']) {
                app(LegalDocumentPublishingService::class)->publish($document);
            }
        } catch (QueryException $e) {
            if ((int) $e->getCode() === 23000) {
                $this->addError('type', 'Conflito de versão automática. Tente novamente.');

                return;
            }

            report($e);
            $this->dispatch('swal:alert', ['icon' => 'error', 'title' => 'Erro', 'text' => 'Não foi possível salvar o documento jurídico.']);

            return;
        } catch (DomainException $e) {
            $this->dispatch('swal:alert', ['icon' => 'error', 'title' => 'Erro', 'text' => $e->getMessage()]);

            return;
        }

        $this->reset(['title', 'content', 'publishNow']);
        $this->selectedDocumentId = null;

        $this->dispatch('swal:alert', [
            'icon'  => 'success',
            'title' => 'Sucesso',
            'text'  => $validated['publishNow']
                ? 'Documento jurídico criado e publicado com sucesso.'
                : 'Documento jurídico salvo como rascunho.',
        ]);
    }

    public function publish(int $documentId): void
    {
        $this->assertAdmin();

        $document = LegalDocument::query()->find($documentId);
        if (! $document) {
            $this->dispatch('swal:alert', ['icon' => 'error', 'title' => 'Erro', 'text' => 'Documento não encontrado.']);

            return;
        }

        try {
            app(LegalDocumentPublishingService::class)->publish($document);
        } catch (DomainException $e) {
            $this->dispatch('swal:alert', ['icon' => 'error', 'title' => 'Ação inválida', 'text' => $e->getMessage()]);

            return;
        }

        $this->dispatch('swal:alert', ['icon' => 'success', 'title' => 'Publicado', 'text' => 'Nova versão publicada e versão ativa anterior desativada.']);
    }

    public function selectDocument(int $documentId): void
    {
        $this->assertAdmin();

        $document = LegalDocument::query()->with('creator:id,name')->find($documentId);
        if (! $document) {
            return;
        }

        $this->dispatch('open-doc-preview',
            title: $document->title,
            type: $document->type->value,
            version: $document->version,
            isActive: $document->is_active,
            isDraft: ! $document->published_at,
            publishedAt: $document->published_at?->format('d/m/Y H:i'),
            creator: $document->creator?->name,
            content: $document->content,
        );
    }

    private function assertAdmin(): void
    {
        abort_unless((bool) Auth::user()?->is_admin, 403);
    }

    private function nextVersionForType(string $type): string
    {
        $latestVersion = LegalDocument::query()
            ->where('type', $type)
            ->orderByDesc('id')
            ->value('version');

        if (! is_string($latestVersion) || $latestVersion === '') {
            return '1.0';
        }

        if (! preg_match('/^(\d+)\.(\d+)$/', $latestVersion, $matches)) {
            return '1.0';
        }

        return sprintf('%d.%d', (int) $matches[1], (int) $matches[2] + 1);
    }

    private function resolveVariables(string $content, string $version): string
    {
        $now = now();

        $map = [
            '{{versao}}'    => $version,
            '{{version}}'   => $version,
            '{{data}}'      => $now->format('d/m/Y'),
            '{{date}}'      => $now->format('d/m/Y'),
            '{{hora}}'      => $now->format('H:i'),
            '{{time}}'      => $now->format('H:i'),
            '{{data_hora}}' => $now->format('d/m/Y \à\s H:i'),
            '{{datetime}}'  => $now->format('d/m/Y H:i'),
            '{{app}}'       => config('app.name', 'Bolão Copa'),
            '{{app_name}}'  => config('app.name', 'Bolão Copa'),
            '{{ano}}'       => $now->format('Y'),
            '{{year}}'      => $now->format('Y'),
        ];

        return str_replace(array_keys($map), array_values($map), $content);
    }

    public function render()
    {
        $documents = LegalDocument::query()
            ->with('creator:id,name')
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) =>
                $q->where('title', 'like', '%'.$this->search.'%')
                  ->orWhere('version', 'like', '%'.$this->search.'%')
            ))
            ->when($this->filterType !== '', fn ($q) => $q->where('type', $this->filterType))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(15);

        $acceptances = UserLegalAcceptance::query()
            ->with(['user:id,name,email', 'legalDocument:id,title,version,type'])
            ->when($this->acceptanceSearch !== '', fn ($q) => $q->whereHas('user', fn ($u) =>
                $u->where('name', 'like', '%'.$this->acceptanceSearch.'%')
                  ->orWhere('email', 'like', '%'.$this->acceptanceSearch.'%')
            ))
            ->orderByDesc('accepted_at')
            ->paginate(20, ['*'], 'acc_page');

        return view('livewire.admin.legaldocumentsmanager', [
            'documents'   => $documents,
            'types'       => LegalDocumentType::cases(),
            'nextVersion' => $this->nextVersionForType($this->type),
            'acceptances' => $acceptances,
        ]);
    }
}

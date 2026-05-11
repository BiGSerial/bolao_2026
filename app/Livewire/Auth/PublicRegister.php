<?php

namespace App\Livewire\Auth;

use App\Enums\LegalDocumentType;
use App\Http\Controllers\Pools\PoolInviteController;
use App\Models\LegalDocument;
use App\Models\User;
use App\Models\UserLegalAcceptance;
use App\Notifications\WelcomeWithTemporaryPasswordNotification;
use App\Services\Email\EmailDispatchGuard;
use App\Services\Legal\LegalAcceptanceEvidenceService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class PublicRegister extends Component
{
    public string $name = '';
    public string $display_name = '';
    public string $email = '';
    public bool $acceptEula = false;
    public bool $acceptPrivacy = false;

    public function mount(Request $request): void
    {
        $invitedEmail = trim((string) $request->query('email'));
        if ($invitedEmail !== '' && filter_var($invitedEmail, FILTER_VALIDATE_EMAIL)) {
            $this->email = mb_strtolower($invitedEmail);
        }
    }

    public function save(): void
    {
        try {
            $this->validate([
                'name'          => ['required', 'string', 'max:120'],
                'display_name'  => ['required', 'string', 'max:80'],
                'email'         => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                'acceptEula'    => ['accepted'],
                'acceptPrivacy' => ['accepted'],
            ], [
                'name.required'             => 'Informe seu nome completo.',
                'name.max'                  => 'O nome não pode ter mais de 120 caracteres.',
                'display_name.required'     => 'Informe seu apelido.',
                'display_name.max'          => 'O apelido não pode ter mais de 80 caracteres.',
                'email.required'            => 'Informe seu e-mail.',
                'email.email'               => 'Informe um e-mail válido.',
                'email.unique'              => 'Este e-mail já está em uso.',
                'acceptEula.accepted'       => 'Você deve aceitar os Termos de Uso.',
                'acceptPrivacy.accepted'    => 'Você deve aceitar a Política de Privacidade.',
            ]);
        } catch (ValidationException $exception) {
            $this->dispatch('swal:alert', [
                'icon' => 'error',
                'title' => 'Não foi possível concluir o cadastro',
                'messages' => $exception->validator->errors()->all(),
            ]);
            throw $exception;
        }

        $temporaryPassword = Str::password(12, symbols: false);

        $user = User::create([
            'name'                => $this->name,
            'display_name'        => $this->display_name,
            'email'               => $this->email,
            'password'            => Hash::make($temporaryPassword),
            'status'              => 'active',
            'must_change_password' => true,
        ]);

        $this->recordLegalAcceptance($user->id);

        $emailGuard = app(EmailDispatchGuard::class);
        $tempLimit = (int) config('email-limits.limits.temporary_password_user_day', 2);
        if (! $emailGuard->attempt('temporary_password', (string) $user->id, $tempLimit, 86400)) {
            $this->dispatch('swal:alert', [
                'icon' => 'warning',
                'title' => 'Limite de envio',
                'messages' => ['A conta foi criada, mas o envio de e-mail está temporariamente limitado. Contate o suporte.'],
            ]);
        } else {
            $user->notify(new WelcomeWithTemporaryPasswordNotification($temporaryPassword));
        }
        event(new Registered($user));
        PoolInviteController::consumePendingInviteForUser($user->id, $user->email);

        session()->flash('register_success', true);
        $this->redirectRoute('login', navigate: true);
    }

    private function recordLegalAcceptance(int $userId): void
    {
        $eula = LegalDocument::query()->active()->ofType(LegalDocumentType::Eula)->first();
        $privacy = LegalDocument::query()->active()->ofType(LegalDocumentType::PrivacyPolicy)->first();
        $request = request();
        $evidenceService = app(LegalAcceptanceEvidenceService::class);

        foreach (array_filter([$eula, $privacy]) as $document) {
            $evidence = $evidenceService->buildEvidence(
                request: $request,
                document: $document,
                method: 'public_registration',
                context: ['source' => 'public_register'],
            );

            UserLegalAcceptance::firstOrCreate(
                ['user_id' => $userId, 'legal_document_id' => $document->id],
                $evidence
            );
        }
    }

    public function render()
    {
        return view('livewire.auth.publicregister');
    }
}

<?php

namespace App\Livewire\Pools;

use App\Events\PoolMembersUpdated;
use App\Events\PoolJoinRequestCreated;
use App\Enums\PoolMemberStatus;
use App\Models\Competition;
use App\Models\Pool;
use App\Models\PoolMember;
use App\Models\PoolRanking;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PoolIndex extends Component
{
    public string $competition_code = '';
    public string $invite_code = '';
    public string $invite_sector = '';
    /** @var string[] */
    public array $invite_sectors = [];
    /** @var array<string,mixed>|null */
    public ?array $invite_pool_preview = null;

    public function mount(): void
    {
        $requested = strtoupper((string) request()->query('competition', session('competition', '')));
        $available = $this->availableCompetitionCodes();
        $defaultCode = strtoupper((string) config('football-data.default_competition_code', 'WC'));

        if ($requested !== '' && in_array($requested, $available, true)) {
            $this->competition_code = $requested;
            return;
        }

        $this->competition_code = in_array($defaultCode, $available, true) ? $defaultCode : ($available[0] ?? 'WC');
        session(['competition' => $this->competition_code]);
    }

    public function updatedInviteCode(string $value): void
    {
        $code = strtoupper(trim($value));
        $this->invite_code = $code;

        if (strlen($code) !== 8) {
            $this->invite_sectors = [];
            $this->invite_sector = '';
            $this->invite_pool_preview = null;
            return;
        }

        $pool = Pool::query()
            ->where('invite_code', $code)
            ->with('competition:id,code,name')
            ->first(['id', 'competition_id', 'name', 'slug', 'status', 'visibility', 'sectors']);
        $this->invite_sectors = $pool && is_array($pool->sectors) ? array_values($pool->sectors) : [];
        $this->invite_pool_preview = null;

        if ($pool) {
            $competitionCode = strtoupper((string) ($pool->competition?->code ?? ''));
            $competitionName = (string) ($pool->competition?->name
                ?? config('football-data.competitions.'.$competitionCode.'.name', $competitionCode));
            $this->invite_pool_preview = [
                'name' => (string) $pool->name,
                'slug' => (string) $pool->slug,
                'status' => (string) $pool->status,
                'visibility' => (string) $pool->visibility,
                'competition_code' => $competitionCode,
                'competition_name' => $competitionName !== '' ? $competitionName : '—',
                'requires_sector' => ! empty($this->invite_sectors),
            ];
        }

        if (! in_array($this->invite_sector, $this->invite_sectors, true)) {
            $this->invite_sector = '';
        }
    }

    public function joinByInviteCode(): void
    {
        $data = $this->validate([
            'invite_code' => ['required', 'string', 'size:8'],
            'invite_sector' => ['nullable', 'string', 'max:80'],
        ]);

        $code = strtoupper(trim($data['invite_code']));
        $pool = Pool::query()->where('invite_code', $code)->where('status', 'active')->first();

        if (! $pool) {
            $this->addError('invite_code', 'Codigo de convite invalido.');
            $this->invite_sectors = [];
            $this->invite_sector = '';
            $this->invite_pool_preview = null;
            return;
        }

        $this->invite_sectors = is_array($pool->sectors) ? array_values($pool->sectors) : [];
        $this->invite_pool_preview = [
            'name' => (string) $pool->name,
            'slug' => (string) $pool->slug,
            'status' => (string) $pool->status,
            'visibility' => (string) $pool->visibility,
            'competition_code' => strtoupper((string) ($pool->competition?->code ?? '')),
            'competition_name' => (string) ($pool->competition?->name
                ?? config('football-data.competitions.'.strtoupper((string) ($pool->competition?->code ?? '')).'.name', '—')),
            'requires_sector' => ! empty($this->invite_sectors),
        ];

        if ($pool->status !== 'active') {
            $this->addError('invite_code', 'Este bolão não está ativo no momento.');
            return;
        }

        if (! empty($this->invite_sectors) && ! in_array($data['invite_sector'] ?? '', $this->invite_sectors, true)) {
            $this->addError('invite_sector', 'Selecione um setor valido para entrar neste bolao.');
            return;
        }

        $userId = (int) Auth::id();
        $member = PoolMember::query()
            ->where('pool_id', $pool->id)
            ->where('user_id', $userId)
            ->first();

        if ($member) {
            if ($member->status === PoolMemberStatus::Active->value) {
                session()->flash('status', 'Voce ja faz parte deste bolao.');
                $this->redirectRoute('pools.show', ['pool' => $pool->slug], navigate: true);
                return;
            }

            if ($member->status === PoolMemberStatus::Pending->value) {
                session()->flash('status', 'Sua solicitacao ja esta em analise. Aguarde aprovacao do gestor.');
                return;
            }

            $member->update([
                'role' => 'member',
                'sector' => ! empty($this->invite_sectors) ? ($data['invite_sector'] ?: null) : null,
                'status' => PoolMemberStatus::Pending->value,
                'activated_by' => null,
                'activated_at' => null,
                'deactivated_by' => null,
                'deactivated_at' => null,
            ]);
            PoolMembersUpdated::dispatch($pool);
            $this->notifyManagersAboutJoinRequest($pool);
            $poolCompetitionCode = strtoupper((string) ($pool->competition?->code ?? ''));
            if ($poolCompetitionCode !== '') {
                session(['competition' => $poolCompetitionCode]);
            }

            session()->flash('status', 'Solicitacao reenviada. Aguarde liberacao do gestor do bolao.');
            $this->invite_code = '';
            $this->invite_sector = '';
            $this->invite_sectors = [];
            $this->invite_pool_preview = null;
            return;
        }

        $pool->members()->create([
            'user_id' => $userId,
            'role' => 'member',
            'sector' => ! empty($this->invite_sectors) ? ($data['invite_sector'] ?: null) : null,
            'status' => PoolMemberStatus::Pending->value,
        ]);
        PoolMembersUpdated::dispatch($pool);
        $this->notifyManagersAboutJoinRequest($pool);
        $poolCompetitionCode = strtoupper((string) ($pool->competition?->code ?? ''));
        if ($poolCompetitionCode !== '') {
            session(['competition' => $poolCompetitionCode]);
        }

        session()->flash('status', 'Solicitacao enviada. Aguarde liberacao do gestor do bolao.');
        $this->invite_code = '';
        $this->invite_sector = '';
        $this->invite_sectors = [];
        $this->invite_pool_preview = null;
    }

    public function canSubmitInviteRequest(): bool
    {
        if (! is_array($this->invite_pool_preview) || empty($this->invite_pool_preview)) {
            return false;
        }

        if (($this->invite_pool_preview['status'] ?? null) !== 'active') {
            return false;
        }

        if (! empty($this->invite_sectors) && ! in_array($this->invite_sector, $this->invite_sectors, true)) {
            return false;
        }

        return true;
    }

    public function requestPublicEntry(int $poolId): void
    {
        $userId = (int) Auth::id();

        $pool = Pool::query()
            ->whereKey($poolId)
            ->where('visibility', 'public')
            ->where('status', 'active')
            ->whereHas('competition', fn ($q) => $q->where('code', $this->competition_code))
            ->first();

        if (! $pool) {
            session()->flash('status', 'Este bolão público não está disponível no momento.');
            return;
        }

        $member = PoolMember::query()
            ->where('pool_id', $pool->id)
            ->where('user_id', $userId)
            ->first();

        if ($member) {
            if ($member->status === PoolMemberStatus::Active->value) {
                session()->flash('status', 'Você já participa deste bolão.');
                $this->redirectRoute('pools.show', ['pool' => $pool->slug], navigate: true);
                return;
            }

            if ($member->status === PoolMemberStatus::Pending->value) {
                session()->flash('status', 'Sua solicitação já está em análise.');
                return;
            }

            $member->update([
                'role' => 'member',
                'sector' => null,
                'status' => PoolMemberStatus::Pending->value,
                'activated_by' => null,
                'activated_at' => null,
                'deactivated_by' => null,
                'deactivated_at' => null,
            ]);
            PoolMembersUpdated::dispatch($pool);
            $this->notifyManagersAboutJoinRequest($pool);
            session()->flash('status', 'Solicitação reenviada. Aguarde aprovação do gestor do bolão.');
            return;
        }

        $pool->members()->create([
            'user_id' => $userId,
            'role' => 'member',
            'sector' => null,
            'status' => PoolMemberStatus::Pending->value,
        ]);
        PoolMembersUpdated::dispatch($pool);
        $this->notifyManagersAboutJoinRequest($pool);

        session()->flash('status', 'Solicitação enviada. Aguarde aprovação do gestor do bolão.');
    }

    public function leavePool(int $poolId): void
    {
        $userId = (int) Auth::id();
        $member = PoolMember::query()
            ->where('pool_id', $poolId)
            ->where('user_id', $userId)
            ->whereIn('status', [PoolMemberStatus::Active->value, PoolMemberStatus::Pending->value])
            ->first();

        if (! $member) {
            session()->flash('status', 'Você não participa deste bolão.');
            return;
        }

        if ($member->role === 'owner') {
            session()->flash('status', 'O dono do bolão não pode sair sem transferir a propriedade.');
            return;
        }

        $member->update([
            'status' => PoolMemberStatus::Removed->value,
            'deactivated_by' => $userId,
            'deactivated_at' => now(),
        ]);

        if ($member->pool) {
            PoolMembersUpdated::dispatch($member->pool);
        }

        session()->flash('status', 'Você saiu do bolão.');
    }

    public function render()
    {
        $userId = (int) Auth::id();

        $myPools = PoolMember::query()
            ->where('user_id', $userId)
            ->whereHas('pool', fn ($q) => $q
                ->whereHas('competition', fn ($q2) => $q2->where('code', $this->competition_code))
            )
            ->with([
                'pool' => fn ($q) => $q
                    ->select('id', 'competition_id', 'name', 'slug', 'status', 'visibility', 'invite_code',
                             'description', 'closed_predictions', 'allow_prediction_changes',
                             'points_exact_score', 'points_correct_result', 'points_correct_goals',
                             'allow_pending_member_predictions')
                    ->withCount([
                        'members',
                        'members as pending_members_count' => fn ($q) => $q->where('status', 'pending'),
                    ]),
            ])
            ->latest('id')
            ->get();

        $myRankings = PoolRanking::query()
            ->where('user_id', $userId)
            ->whereIn('pool_id', $myPools->pluck('pool_id')->all())
            ->get(['pool_id', 'position', 'points_total'])
            ->keyBy('pool_id');

        $publicPools = Pool::query()
            ->where('visibility', 'public')
            ->where('status', 'active')
            ->whereHas('competition', fn ($q) => $q->where('code', $this->competition_code))
            ->whereDoesntHave('members', fn ($q) => $q->where('user_id', $userId))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'slug', 'description', 'status', 'visibility']);

        session(['competition' => $this->competition_code]);
        $competitionName = (string) config('football-data.competitions.'.$this->competition_code.'.name', $this->competition_code);

        return view('livewire.pools.poolindex', compact('myPools', 'myRankings', 'publicPools', 'competitionName'));
    }

    /**
     * @return string[]
     */
    private function availableCompetitionCodes(): array
    {
        $user = Auth::user();
        $configured = array_keys((array) config('football-data.competitions', []));
        $fromDb = Competition::query()->whereNotNull('code')->pluck('code')->map(fn ($code) => strtoupper((string) $code))->all();

        $all = array_unique(array_map('strtoupper', array_merge($configured, $fromDb)));
        $all = array_values(array_filter($all, fn (string $code) => $user ? $user->canAccessCompetition($code) : $code === 'WC'));
        sort($all);

        return $all !== [] ? $all : ['WC'];
    }

    private function notifyManagersAboutJoinRequest(Pool $pool): void
    {
        $pendingCount = (int) $pool->members()->where('status', PoolMemberStatus::Pending->value)->count();

        $managerIds = $pool->members()
            ->where('status', PoolMemberStatus::Active->value)
            ->whereIn('role', ['owner', 'manager'])
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        foreach ($managerIds as $managerId) {
            PoolJoinRequestCreated::dispatch($managerId, (int) $pool->id, $pendingCount);
        }
    }
}

<?php

namespace App\Livewire\Pools;

use App\Events\PoolMembersUpdated;
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

        if (strlen($code) !== 8) {
            $this->invite_sectors = [];
            $this->invite_sector = '';
            return;
        }

        $pool = Pool::query()
            ->where('invite_code', $code)
            ->where('status', 'active')
            ->whereHas('competition', fn ($q) => $q->where('code', $this->competition_code))
            ->first(['id', 'sectors']);
        $this->invite_sectors = $pool && is_array($pool->sectors) ? array_values($pool->sectors) : [];

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
            return;
        }

        if (($pool->competition?->code ?? null) !== $this->competition_code) {
            $this->addError('invite_code', 'Este código pertence a outra competição.');
            return;
        }

        $this->invite_sectors = is_array($pool->sectors) ? array_values($pool->sectors) : [];

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
            session()->flash('status', 'Voce ja faz parte deste bolao.');
            $this->redirectRoute('pools.show', ['pool' => $pool->slug], navigate: true);
            return;
        }

        $pool->members()->create([
            'user_id' => $userId,
            'role' => 'member',
            'sector' => ! empty($this->invite_sectors) ? ($data['invite_sector'] ?: null) : null,
            'status' => PoolMemberStatus::Pending->value,
        ]);
        PoolMembersUpdated::dispatch($pool);

        session()->flash('status', 'Solicitacao enviada. Aguarde liberacao do gestor do bolao.');
        $this->invite_code = '';
        $this->invite_sector = '';
        $this->invite_sectors = [];
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
            session()->flash('status', 'Você já possui participação solicitada/ativa neste bolão.');
            if ($member->status === PoolMemberStatus::Active->value) {
                $this->redirectRoute('pools.show', ['pool' => $pool->slug], navigate: true);
            }
            return;
        }

        $pool->members()->create([
            'user_id' => $userId,
            'role' => 'member',
            'sector' => null,
            'status' => PoolMemberStatus::Pending->value,
        ]);
        PoolMembersUpdated::dispatch($pool);

        session()->flash('status', 'Solicitação enviada. Aguarde aprovação do gestor do bolão.');
    }

    public function render()
    {
        $userId = (int) Auth::id();

        $myPools = PoolMember::query()
            ->where('user_id', $userId)
            ->whereHas('pool.competition', fn ($q) => $q->where('code', $this->competition_code))
            ->with('pool:id,competition_id,name,slug,status,visibility,invite_code')
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
}

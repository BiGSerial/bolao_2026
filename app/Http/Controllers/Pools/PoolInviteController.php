<?php

namespace App\Http\Controllers\Pools;

use App\Events\PoolMembersUpdated;
use App\Enums\PoolMemberStatus;
use App\Http\Controllers\Controller;
use App\Models\PoolInvite;
use App\Models\PoolMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PoolInviteController extends Controller
{
    public function accept(Request $request, string $token): RedirectResponse
    {
        $invite = PoolInvite::query()
            ->where('token', $token)
            ->where('status', 'pending')
            ->first();

        if (! $invite || ($invite->expires_at && $invite->expires_at->isPast())) {
            return redirect()->route('login')->with('status', 'Convite inválido ou expirado.');
        }

        if (! Auth::check()) {
            session()->put('pool_invite_token', $invite->token);
            $this->storeInviteRedirectContext($invite);
            return redirect()->route('register', ['email' => $invite->email]);
        }

        $this->attachUserToPool(Auth::user()->id, $invite);
        $this->storeInviteRedirectContext($invite);

        return redirect()->route('pools.show', ['pool' => $invite->pool->slug])
            ->with('status', 'Você entrou no bolão com sucesso!');
    }

    public static function consumePendingInviteForUser(int $userId, string $email): void
    {
        $invite = null;
        $token = session()->pull('pool_invite_token');

        if ($token) {
            $invite = PoolInvite::query()->where('token', $token)->where('status', 'pending')->first();
        }

        if (! $invite) {
            $invite = PoolInvite::query()
                ->where('email', $email)
                ->where('status', 'pending')
                ->orderByDesc('id')
                ->first();
        }

        if (! $invite || ($invite->expires_at && $invite->expires_at->isPast())) {
            return;
        }

        $controller = new self();
        $controller->attachUserToPool($userId, $invite);
        $controller->storeInviteRedirectContext($invite);
    }

    private function attachUserToPool(int $userId, PoolInvite $invite): void
    {
        $member = PoolMember::query()
            ->where('pool_id', $invite->pool_id)
            ->where('user_id', $userId)
            ->first();

        if (! $member) {
            PoolMember::create([
                'pool_id' => $invite->pool_id,
                'user_id' => $userId,
                'role' => 'member',
                'sector' => $invite->sector,
                'status' => PoolMemberStatus::Active->value,
                'activated_by' => $invite->invited_by,
                'activated_at' => now(),
            ]);
        }

        $invite->update([
            'status' => 'accepted',
            'accepted_by' => $userId,
            'accepted_at' => now(),
        ]);

        PoolMembersUpdated::dispatch($invite->pool);
    }

    private function storeInviteRedirectContext(PoolInvite $invite): void
    {
        $pool = $invite->pool()->with('competition:id,code')->first();
        if (! $pool) {
            return;
        }

        $competitionCode = strtoupper((string) ($pool->competition?->code ?? ''));
        if ($competitionCode !== '') {
            session(['competition' => $competitionCode]);
        }

        session([
            'pool_invite_redirect' => [
                'pool' => $pool->slug,
                'competition' => $competitionCode,
            ],
        ]);
    }
}

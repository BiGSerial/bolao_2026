<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PoolMemberRole;
use App\Enums\PoolMemberStatus;
use App\Models\Competition;
use App\Models\CompetitionSeason;
use App\Models\Pool;
use App\Models\PoolChatMessage;
use App\Models\PoolMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PoolChatApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_can_edit_message_within_whatsapp_window_and_audit_is_available_to_admin(): void
    {
        [$pool, $author, $admin] = $this->basePoolWithUsers();
        $token = $author->createToken('author-device')->plainTextToken;

        $create = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/pools/{$pool->id}/chat/messages", [
                'body' => 'Mensagem original',
            ]);

        $messageId = (int) $create->json('data.message.id');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson("/api/v1/pools/{$pool->id}/chat/messages/{$messageId}", [
                'body' => 'Mensagem editada',
            ])
            ->assertOk()
            ->assertJsonPath('data.message.body', 'Mensagem editada')
            ->assertJsonPath('data.message.edited_at', fn ($value) => is_string($value) && $value !== '');

        $adminToken = $admin->createToken('admin-device')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->getJson("/api/v1/pools/{$pool->id}/chat/messages")
            ->assertOk()
            ->assertJsonPath('data.items.0.audit.edits.0.old_body', 'Mensagem original')
            ->assertJsonPath('data.items.0.audit.edits.0.new_body', 'Mensagem editada');
    }

    public function test_author_cannot_edit_message_after_15_minutes(): void
    {
        [$pool, $author] = $this->basePoolWithUsers();
        $message = PoolChatMessage::query()->create([
            'pool_id' => $pool->id,
            'user_id' => $author->id,
            'body' => 'Antiga',
        ]);
        $message->forceFill([
            'created_at' => now()->subMinutes(16),
            'updated_at' => now()->subMinutes(16),
        ])->save();

        $token = $author->createToken('author-device')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson("/api/v1/pools/{$pool->id}/chat/messages/{$message->id}", [
                'body' => 'Tentativa tardia',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'CHAT_EDIT_WINDOW_EXPIRED');
    }

    public function test_deleted_message_is_hidden_for_regular_users_and_visible_to_admin_for_audit(): void
    {
        [$pool, $author, $admin] = $this->basePoolWithUsers();
        $message = PoolChatMessage::query()->create([
            'pool_id' => $pool->id,
            'user_id' => $author->id,
            'body' => 'Conteudo abusivo',
        ]);

        $authorToken = $author->createToken('author-device')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$authorToken)
            ->deleteJson("/api/v1/pools/{$pool->id}/chat/messages/{$message->id}")
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$authorToken)
            ->getJson("/api/v1/pools/{$pool->id}/chat/messages")
            ->assertOk()
            ->assertJsonPath('data.items.0.body', 'Mensagem apagada')
            ->assertJsonPath('data.items.0.audit', null);

        $adminToken = $admin->createToken('admin-device')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$adminToken)
            ->getJson("/api/v1/pools/{$pool->id}/chat/messages")
            ->assertOk()
            ->assertJsonPath('data.items.0.body', 'Conteudo abusivo')
            ->assertJsonPath('data.items.0.audit.deleted_body', 'Conteudo abusivo')
            ->assertJsonPath('data.items.0.audit.deleted_by.id', $author->id);
    }

    private function basePoolWithUsers(): array
    {
        $owner = User::factory()->create();
        $author = User::factory()->create();
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $competition = Competition::query()->create([
            'provider' => 'football_data',
            'external_id' => random_int(1000, 9999),
            'code' => 'WC',
            'name' => 'World Cup',
        ]);

        $season = CompetitionSeason::query()->create([
            'competition_id' => $competition->id,
            'provider' => 'football_data',
            'external_id' => random_int(10000, 19999),
            'year' => 2026,
        ]);

        $pool = Pool::query()->create([
            'owner_id' => $owner->id,
            'competition_id' => $competition->id,
            'competition_season_id' => $season->id,
            'name' => 'Chat Pool',
            'slug' => 'chat-pool-'.Str::lower(Str::random(6)),
            'visibility' => 'invite_only',
            'status' => 'active',
            'invite_code' => Str::upper(Str::random(8)),
        ]);

        foreach ([[$owner, PoolMemberRole::Owner], [$author, PoolMemberRole::Member]] as [$user, $role]) {
            PoolMember::query()->create([
                'pool_id' => $pool->id,
                'user_id' => $user->id,
                'role' => $role->value,
                'status' => PoolMemberStatus::Active->value,
            ]);
        }

        return [$pool, $author, $admin];
    }
}

<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Tests\TestCase;

class NotificationsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_notifications_with_pagination_and_filter(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        $user->notify(new class('primeira', false) extends Notification {
            use Queueable;

            public function __construct(private readonly string $title, private readonly bool $markRead) {}

            public function via(object $notifiable): array
            {
                return ['database'];
            }

            public function toArray(object $notifiable): array
            {
                return ['title' => $this->title, 'mark_read' => $this->markRead];
            }
        });

        $second = new class('segunda', true) extends Notification {
            use Queueable;

            public function __construct(private readonly string $title, private readonly bool $markRead) {}

            public function via(object $notifiable): array
            {
                return ['database'];
            }

            public function toArray(object $notifiable): array
            {
                return ['title' => $this->title, 'mark_read' => $this->markRead];
            }
        };

        $user->notify($second);
        $last = $user->notifications()->latest('created_at')->first();
        $last?->markAsRead();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/notifications?per_page=10');

        $response
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 2)
            ->assertJsonCount(2, 'data.items');

        $unreadOnly = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/notifications?unread_only=1');

        $unreadOnly
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.read_at', null);
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        $user->notify(new class extends Notification {
            use Queueable;

            public function via(object $notifiable): array
            {
                return ['database'];
            }

            public function toArray(object $notifiable): array
            {
                return ['title' => 'pending'];
            }
        });

        $notification = $user->notifications()->latest('created_at')->firstOrFail();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/notifications/'.$notification->id.'/read');

        $response
            ->assertOk()
            ->assertJsonPath('data.id', (string) $notification->id);

        $this->assertNotNull($user->notifications()->find($notification->id)?->read_at);
    }

    public function test_user_cannot_mark_another_user_notification_as_read(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $token = $user->createToken('test-device')->plainTextToken;

        $other->notify(new class extends Notification {
            use Queueable;

            public function via(object $notifiable): array
            {
                return ['database'];
            }

            public function toArray(object $notifiable): array
            {
                return ['title' => 'other'];
            }
        });

        $otherNotification = $other->notifications()->latest('created_at')->firstOrFail();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/notifications/'.$otherNotification->id.'/read');

        $response
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NOTIFICATION_NOT_FOUND');
    }
}

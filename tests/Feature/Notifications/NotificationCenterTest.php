<?php

namespace Tests\Feature\Notifications;

use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Technical\Notifications\Livewire\NotificationCenter;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    private function seedNotification(User $user, string $title, ?string $readAt = null): string
    {
        $id = (string) Str::uuid();
        $user->notifications()->create([
            'id' => $id,
            'type' => 'Functional\\Todo\\Notifications\\TaskReminderNotification',
            'data' => ['title' => $title],
            'read_at' => $readAt,
        ]);

        return $id;
    }

    #[Test]
    public function it_counts_only_the_users_unread_notifications(): void
    {
        $user = User::factory()->create();
        $this->seedNotification($user, 'Loyer dû');
        $this->seedNotification($user, 'Déjà lue', now()->toDateTimeString());
        $this->seedNotification(User::factory()->create(), 'Autre user');

        Livewire::actingAs($user, 'web')
            ->test(NotificationCenter::class)
            ->assertSet('unreadCount', 1)
            ->assertSee('Loyer dû');
    }

    #[Test]
    public function it_marks_a_single_notification_as_read(): void
    {
        $user = User::factory()->create();
        $id = $this->seedNotification($user, 'À lire');

        Livewire::actingAs($user, 'web')
            ->test(NotificationCenter::class)
            ->call('markAsRead', $id)
            ->assertSet('unreadCount', 0);

        $this->assertNotNull($user->notifications()->find($id)->read_at);
    }

    #[Test]
    public function it_marks_all_notifications_as_read(): void
    {
        $user = User::factory()->create();
        $this->seedNotification($user, 'Une');
        $this->seedNotification($user, 'Deux');

        Livewire::actingAs($user, 'web')
            ->test(NotificationCenter::class)
            ->call('markAllAsRead')
            ->assertSet('unreadCount', 0);

        $this->assertSame(0, $user->unreadNotifications()->count());
    }
}

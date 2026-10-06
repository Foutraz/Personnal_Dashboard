<?php

namespace Tests\Feature\Notifications;

use Functional\Todo\Models\Task;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
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

    private function seedForeignNotification(string $notifiableId, string $title): string
    {
        $id = (string) Str::uuid();
        DatabaseNotification::query()->create([
            'id' => $id,
            'type' => 'Functional\\Todo\\Notifications\\TaskReminderNotification',
            'data' => ['title' => $title],
            'notifiable_type' => Task::class,
            'notifiable_id' => $notifiableId,
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

    #[Test]
    public function it_ignores_the_notifications_of_another_notifiable_type_sharing_the_user_id(): void
    {
        $user = User::factory()->create();
        $this->seedNotification($user, 'Loyer dû');
        $this->seedForeignNotification($user->id, 'Intrus');

        $component = Livewire::actingAs($user, 'web')
            ->test(NotificationCenter::class)
            ->assertSet('unreadCount', 1)
            ->assertSee('Loyer dû')
            ->assertDontSee('Intrus');

        $this->assertCount(1, $component->instance()->recent);
    }

    #[Test]
    public function it_does_not_mark_as_read_a_notification_of_another_notifiable_type_sharing_the_user_id(): void
    {
        $user = User::factory()->create();
        $foreignId = $this->seedForeignNotification($user->id, 'Intrus');

        Livewire::actingAs($user, 'web')
            ->test(NotificationCenter::class)
            ->call('markAsRead', $foreignId);

        $this->assertNull(DatabaseNotification::query()->findOrFail($foreignId)->read_at);
    }

    #[Test]
    public function it_does_not_mark_all_as_read_the_notifications_of_another_notifiable_type_sharing_the_user_id(): void
    {
        $user = User::factory()->create();
        $this->seedNotification($user, 'Une');
        $this->seedNotification($user, 'Deux');
        $foreignId = $this->seedForeignNotification($user->id, 'Intrus');

        Livewire::actingAs($user, 'web')
            ->test(NotificationCenter::class)
            ->call('markAllAsRead');

        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertNull(DatabaseNotification::query()->findOrFail($foreignId)->read_at);
    }
}

<?php

namespace Tests\Feature\Notifications;

use Functional\Gamification\Notifications\BadgeAwardedNotification;
use Functional\RecurringExpenses\Notifications\ExpenseDueReminderNotification;
use Functional\Todo\Models\Task;
use Functional\Todo\Notifications\TaskReminderNotification;
use Functional\Users\Events\UserDeleting;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Technical\Notifications\Listeners\DeleteUserNotifications;
use Tests\TestCase;

class UserDeletionPurgeTest extends TestCase
{
    use RefreshDatabase;

    private function seedNotification(User $user, string $type): DatabaseNotification
    {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => $type,
            'data' => ['title' => faker()->words(2)],
        ]);
    }

    private function seedThreeNotifications(User $user): void
    {
        $this->seedNotification($user, TaskReminderNotification::class);
        $this->seedNotification($user, ExpenseDueReminderNotification::class);
        $this->seedNotification($user, BadgeAwardedNotification::class);
    }

    #[Test]
    public function it_purges_every_notification_of_the_deleted_user_whatever_its_type(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $this->seedThreeNotifications($userA);
        $this->seedNotification($userB, TaskReminderNotification::class);
        $this->seedNotification($userB, BadgeAwardedNotification::class);

        $userA->delete();

        $this->assertSoftDeleted($userA);
        $this->assertSame(0, DatabaseNotification::query()->whereMorphedTo('notifiable', $userA)->count());
        $this->assertSame(2, DatabaseNotification::query()->whereMorphedTo('notifiable', $userB)->count());
    }

    #[Test]
    public function it_keeps_the_notifications_of_another_notifiable_type_sharing_the_user_id(): void
    {
        $userA = User::factory()->create();
        $foreignNotification = DatabaseNotification::query()->create([
            'id' => (string) Str::uuid(),
            'type' => TaskReminderNotification::class,
            'data' => ['title' => faker()->words(2)],
            'notifiable_type' => Task::class,
            'notifiable_id' => $userA->id,
        ]);

        $userA->delete();

        $this->assertModelExists($foreignNotification);
    }

    #[Test]
    public function it_purges_the_notifications_one_row_at_a_time_through_eloquent(): void
    {
        $deletedCount = 0;
        DatabaseNotification::deleted(function () use (&$deletedCount) {
            $deletedCount++;
        });
        $userA = User::factory()->create();
        $this->seedNotification($userA, TaskReminderNotification::class);
        $this->seedNotification($userA, TaskReminderNotification::class);
        $this->seedNotification($userA, ExpenseDueReminderNotification::class);

        $userA->delete();

        $this->assertSame(3, $deletedCount);
    }

    #[Test]
    public function it_listens_to_the_user_deleting_event(): void
    {
        Event::fake();

        Event::assertListening(UserDeleting::class, DeleteUserNotifications::class);
    }
}

<?php

namespace Tests\Feature\Notifications;

use Functional\Todo\Notifications\TaskReminderNotification;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserDeletionPurgeHaltedDeleteTest extends TestCase
{
    use RefreshDatabase;

    private const NOTIFICATIONS = 3;

    private function seedNotifications(User $user): void
    {
        for ($count = 1; $count <= self::NOTIFICATIONS; $count++) {
            $user->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => TaskReminderNotification::class,
                'data' => ['title' => faker()->words(2)],
            ]);
        }
    }

    #[Test]
    public function it_keeps_purging_the_remaining_notifications_when_a_deleting_listener_halts_one_delete(): void
    {
        $alreadyHalted = false;
        DatabaseNotification::deleting(function () use (&$alreadyHalted): ?bool {
            if ($alreadyHalted) {
                return null;
            }

            $alreadyHalted = true;

            return false;
        });
        $user = User::factory()->create();
        $this->seedNotifications($user);

        $user->delete();

        $this->assertSame(1, DatabaseNotification::query()->whereMorphedTo('notifiable', $user)->count());
    }
}

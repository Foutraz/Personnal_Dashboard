<?php

namespace Tests\Feature\Notifications;

use Functional\Todo\Models\Task;
use Functional\Todo\Notifications\TaskReminderNotification;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationsSchemaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_declares_the_notifiable_id_as_a_string_column(): void
    {
        $this->assertContains(Schema::getColumnType('notifications', 'notifiable_id'), ['char', 'varchar']);
    }

    #[Test]
    public function it_keeps_the_composite_notifiable_index(): void
    {
        $indexedColumns = collect(Schema::getIndexes('notifications'))->pluck('columns')->all();

        $this->assertContains(['notifiable_type', 'notifiable_id'], $indexedColumns);
    }

    #[Test]
    public function it_stores_a_notification_for_a_ulid_user(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);

        $user->notify(new TaskReminderNotification($task));

        $this->assertSame($user->id, DatabaseNotification::query()->sole()->notifiable_id);
        $this->assertSame(1, $user->notifications()->count());
    }
}

<?php

namespace Tests\Feature\Todo;

use Carbon\CarbonPeriod;
use Functional\Todo\Dashboard\TodoAgendaProvider;
use Functional\Todo\Models\Task;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TodoAgendaProviderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_uncompleted_due_tasks_within_the_period(): void
    {
        $user = User::factory()->create();
        Task::factory()->create(['user_id' => $user->id, 'due_at' => Carbon::parse('2026-07-05 12:00')]);
        Task::factory()->completed()->create(['user_id' => $user->id, 'due_at' => Carbon::parse('2026-07-06 12:00')]);
        Task::factory()->create(['user_id' => $user->id, 'due_at' => null]);
        Task::factory()->create(['user_id' => $user->id, 'due_at' => Carbon::parse('2026-12-01 12:00')]);
        Task::factory()->create(['user_id' => User::factory()->create()->id, 'due_at' => Carbon::parse('2026-07-07 12:00')]);

        $items = $this->app->make(TodoAgendaProvider::class)->agendaItems($user, CarbonPeriod::create('2026-07-01', '2026-07-31'));

        $this->assertCount(1, $items);
        $this->assertSame('task', $items->first()->source);
    }
}

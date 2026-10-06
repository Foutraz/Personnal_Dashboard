<?php

namespace Tests\Feature\Todo;

use Carbon\CarbonImmutable;
use Functional\Todo\Livewire\TodoBoard;
use Functional\Todo\Models\Task;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TodoBoardTimezoneTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[DataProvider('localDueDates')]
    public function it_stores_a_local_due_date_as_the_utc_instant(string $displayTimezone, string $localDueAt, string $expectedUtc): void
    {
        Config::set('app.display_timezone', $displayTimezone);
        $user = User::factory()->create();

        Livewire::actingAs($user, 'web')
            ->test(TodoBoard::class)
            ->set('newTitle', 'Appeler le garagiste')
            ->set('newDueAt', $localDueAt)
            ->call('createTask')
            ->assertHasNoErrors()
            ->assertSet('newDueAt', null);

        $this->assertSame($expectedUtc, Task::query()->sole()->due_at->toDateTimeString());
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function localDueDates(): array
    {
        return [
            'Paris summer time' => ['Europe/Paris', '2026-10-04T23:30', '2026-10-04 21:30:00'],
            'Paris winter time' => ['Europe/Paris', '2026-12-06T23:30', '2026-12-06 22:30:00'],
            'New York behind UTC' => ['America/New_York', '2026-10-04T23:30', '2026-10-05 03:30:00'],
        ];
    }

    #[Test]
    public function it_leaves_the_due_date_empty_when_none_is_given(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user, 'web')
            ->test(TodoBoard::class)
            ->set('newTitle', 'Appeler le garagiste')
            ->set('newDueAt', '')
            ->call('createTask')
            ->assertHasNoErrors();

        $this->assertNull(Task::query()->sole()->due_at);
    }

    #[Test]
    public function it_still_rejects_an_unparsable_due_date(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user, 'web')
            ->test(TodoBoard::class)
            ->set('newTitle', 'Appeler le garagiste')
            ->set('newDueAt', 'not-a-date')
            ->call('createTask')
            ->assertHasErrors(['newDueAt' => 'date']);

        $this->assertSame(0, Task::query()->count());
    }

    #[Test]
    public function it_lists_the_due_date_in_the_display_timezone(): void
    {
        $user = User::factory()->create();
        Task::factory()->create([
            'user_id' => $user->id,
            'due_at' => CarbonImmutable::parse('2026-10-04 21:30:00', 'UTC'),
        ]);

        Livewire::actingAs($user, 'web')
            ->test(TodoBoard::class)
            ->assertSee('04/10/2026 23:30')
            ->assertDontSee('04/10/2026 21:30');
    }

    #[Test]
    public function it_lists_the_due_date_across_midnight_in_the_display_timezone(): void
    {
        $user = User::factory()->create();
        Task::factory()->create([
            'user_id' => $user->id,
            'due_at' => CarbonImmutable::parse('2026-12-06 23:30:00', 'UTC'),
        ]);

        Livewire::actingAs($user, 'web')
            ->test(TodoBoard::class)
            ->assertSee('07/12/2026 00:30')
            ->assertDontSee('06/12/2026 23:30');
    }

    #[Test]
    public function it_shows_back_the_due_date_that_was_typed(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user, 'web')
            ->test(TodoBoard::class)
            ->set('newTitle', 'Appeler le garagiste')
            ->set('newDueAt', '2026-10-04T23:30')
            ->call('createTask')
            ->assertSee('04/10/2026 23:30');
    }
}

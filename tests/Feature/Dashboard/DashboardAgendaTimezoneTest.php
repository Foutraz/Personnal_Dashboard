<?php

namespace Tests\Feature\Dashboard;

use Carbon\CarbonImmutable;
use Functional\RecurringExpenses\Models\RecurringExpense;
use Functional\Todo\Models\Task;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Technical\WebAuthentication\Livewire\Dashboard;
use Tests\TestCase;

class DashboardAgendaTimezoneTest extends TestCase
{
    use RefreshDatabase;

    private const DAY_MARKUP = 'leading-none">%s</span>';

    private function dashboardWithTaskDueAt(string $utcDueAt): Testable
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-03 08:00:00', 'UTC'));
        $user = User::factory()->create();
        Task::factory()->create(['user_id' => $user->id, 'title' => 'Payer le loyer', 'due_at' => CarbonImmutable::parse($utcDueAt, 'UTC')]);

        return Livewire::actingAs($user, 'web')->test(Dashboard::class);
    }

    #[Test]
    public function it_renders_the_agenda_hour_in_the_display_timezone(): void
    {
        $dashboard = $this->dashboardWithTaskDueAt('2026-10-04 21:30:00');

        $dashboard
            ->assertSeeHtml(sprintf(self::DAY_MARKUP, '4'))
            ->assertSee('23:30')
            ->assertDontSee('21:30');
    }

    #[Test]
    public function it_renders_the_agenda_day_of_an_item_crossing_midnight_in_the_display_timezone(): void
    {
        $dashboard = $this->dashboardWithTaskDueAt('2026-10-04 22:30:00');

        $dashboard
            ->assertSeeHtml(sprintf(self::DAY_MARKUP, '5'))
            ->assertDontSeeHtml(sprintf(self::DAY_MARKUP, '4'))
            ->assertSee('00:30')
            ->assertDontSee('22:30');
    }

    #[Test]
    public function it_renders_the_agenda_month_of_an_item_crossing_a_month_end_in_the_display_timezone(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-29 08:00:00', 'UTC'));
        $user = User::factory()->create();
        Task::factory()->create(['user_id' => $user->id, 'due_at' => CarbonImmutable::parse('2026-10-31 23:30:00', 'UTC')]);

        Livewire::actingAs($user, 'web')
            ->test(Dashboard::class)
            ->assertSeeHtml(sprintf(self::DAY_MARKUP, '1'))
            ->assertSeeHtml('>'.CarbonImmutable::parse('2026-11-01')->isoFormat('MMM').'</span>')
            ->assertDontSeeHtml('>'.CarbonImmutable::parse('2026-10-01')->isoFormat('MMM').'</span>');
    }

    #[Test]
    public function it_renders_the_agenda_day_in_a_display_timezone_behind_utc(): void
    {
        Config::set('app.display_timezone', 'America/New_York');
        $dashboard = $this->dashboardWithTaskDueAt('2026-10-05 02:30:00');

        $dashboard
            ->assertSeeHtml(sprintf(self::DAY_MARKUP, '4'))
            ->assertSee('22:30')
            ->assertDontSee('02:30');
    }

    #[Test]
    public function it_keeps_the_calendar_day_of_an_all_day_item_in_a_display_timezone_behind_utc(): void
    {
        Config::set('app.display_timezone', 'America/New_York');
        $this->travelTo(CarbonImmutable::parse('2026-10-03 08:00:00', 'UTC'));
        $user = User::factory()->create();
        RecurringExpense::factory()->create([
            'user_id' => $user->id,
            'active' => true,
            'next_due_at' => CarbonImmutable::parse('2026-10-05 00:00:00', 'UTC'),
        ]);

        Livewire::actingAs($user, 'web')
            ->test(Dashboard::class)
            ->assertSeeHtml(sprintf(self::DAY_MARKUP, '5'))
            ->assertSee('Toute la journée');
    }
}

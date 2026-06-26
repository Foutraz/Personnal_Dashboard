<?php

namespace Tests\Feature\Planning;

use Functional\Planning\Livewire\PlanningDashboard;
use Functional\Planning\Models\CalendarEvent;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Models\IntegrationConnection;
use Tests\TestCase;

class PlanningDashboardComponentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_shows_the_connect_state_for_both_providers_when_none_exist(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user, 'web')
            ->test(PlanningDashboard::class)
            ->assertOk()
            ->assertSee('Connecter Google Calendar')
            ->assertSee('Connecter Outlook')
            ->assertDontSee('Synchroniser');
    }

    #[Test]
    public function it_shows_the_connected_state_and_renders_calendar_events(): void
    {
        $user = User::factory()->create();
        $connection = IntegrationConnection::factory()->for($user)->create(['provider' => IntegrationProvider::GoogleCalendar]);

        CalendarEvent::factory()->for($connection, 'connection')->create([
            'user_id' => $user->id,
            'provider' => IntegrationProvider::GoogleCalendar,
            'title' => 'Quarterly review',
            'starts_at' => Carbon::now()->addDays(2),
        ]);

        Livewire::actingAs($user, 'web')
            ->test(PlanningDashboard::class)
            ->assertOk()
            ->assertSee('Connecté')
            ->assertSee('Synchroniser')
            ->assertSee('Quarterly review');
    }

    #[Test]
    public function it_switches_between_month_and_week_views(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user, 'web')
            ->test(PlanningDashboard::class)
            ->assertSet('view', 'month')
            ->call('setView', 'week')
            ->assertSet('view', 'week')
            ->call('setView', 'month')
            ->assertSet('view', 'month');
    }
}

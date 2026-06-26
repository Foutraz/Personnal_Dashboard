<?php

namespace Tests\Feature\Dashboard;

use Functional\Sport\Models\SportActivity;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Models\IntegrationConnection;
use Technical\WebAuthentication\Livewire\Dashboard;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_reports_the_real_available_module_count(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user, 'web')
            ->test(Dashboard::class)
            ->assertViewHas('stats', fn (array $stats): bool => $stats['modules_available'] === 7 && $stats['modules_total'] === 8);
    }

    #[Test]
    public function it_reports_synced_activities_and_the_latest_one(): void
    {
        $user = User::factory()->create();
        $connection = IntegrationConnection::factory()->for($user)->create(['provider' => IntegrationProvider::Strava]);

        SportActivity::factory()->count(2)->create([
            'user_id' => $user->id,
            'integration_connection_id' => $connection->id,
            'started_at' => now()->subWeek(),
        ]);
        SportActivity::factory()->create([
            'user_id' => $user->id,
            'integration_connection_id' => $connection->id,
            'name' => 'Morning Run',
            'started_at' => now(),
        ]);

        Livewire::actingAs($user, 'web')
            ->test(Dashboard::class)
            ->assertViewHas('stats', fn (array $stats): bool => $stats['activities_count'] === 3 && $stats['integrations_count'] === 1)
            ->assertSee('Morning Run')
            ->assertSee('Strava');
    }

    #[Test]
    public function it_scopes_the_stats_to_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $otherConnection = IntegrationConnection::factory()->for($other)->create();

        SportActivity::factory()->count(3)->create([
            'user_id' => $other->id,
            'integration_connection_id' => $otherConnection->id,
        ]);

        Livewire::actingAs($user, 'web')
            ->test(Dashboard::class)
            ->assertViewHas('stats', fn (array $stats): bool => $stats['activities_count'] === 0 && $stats['integrations_count'] === 0);
    }
}

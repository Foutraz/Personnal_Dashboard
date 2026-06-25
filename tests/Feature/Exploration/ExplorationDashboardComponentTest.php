<?php

namespace Tests\Feature\Exploration;

use Functional\Exploration\Livewire\ExplorationDashboard;
use Functional\Exploration\Models\ExploredCell;
use Functional\Sport\Models\SportActivity;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Technical\Integrations\Models\IntegrationConnection;
use Tests\TestCase;

class ExplorationDashboardComponentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_renders_the_exploration_dashboard(): void
    {
        $user = User::factory()->create();
        ExploredCell::factory()->count(3)->create(['user_id' => $user->id]);

        Livewire::actingAs($user)
            ->test(ExplorationDashboard::class)
            ->assertOk()
            ->assertSee('Cellules explorées');
    }

    #[Test]
    public function it_rebuilds_the_coverage_when_recalculating(): void
    {
        $user = User::factory()->create();
        $connection = IntegrationConnection::factory()->for($user)->create();

        SportActivity::factory()->create([
            'user_id' => $user->id,
            'integration_connection_id' => $connection->id,
            'map_polyline' => '_p~iF~ps|U_ulLnnqC_mqNvxq`@',
        ]);

        Livewire::actingAs($user)
            ->test(ExplorationDashboard::class)
            ->call('recalculate');

        $this->assertSame(3, ExploredCell::query()->where('user_id', $user->id)->count());
    }
}

<?php

namespace Tests\Feature\Exploration;

use Functional\Exploration\Actions\RebuildUserCoverage;
use Functional\Exploration\Models\ExploredCell;
use Functional\Sport\Models\SportActivity;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Technical\Integrations\Models\IntegrationConnection;
use Tests\TestCase;

class RebuildCoverageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_builds_explored_cells_from_activity_polylines(): void
    {
        $user = User::factory()->create();
        $connection = IntegrationConnection::factory()->for($user)->create();

        SportActivity::factory()->create([
            'user_id' => $user->id,
            'integration_connection_id' => $connection->id,
            'map_polyline' => '_p~iF~ps|U_ulLnnqC_mqNvxq`@',
        ]);

        $count = app(RebuildUserCoverage::class)->handle($user->id);

        $this->assertSame(3, $count);
        $this->assertSame(3, ExploredCell::query()->where('user_id', $user->id)->count());
    }

    #[Test]
    public function it_is_idempotent_when_rebuilt_twice(): void
    {
        $user = User::factory()->create();
        $connection = IntegrationConnection::factory()->for($user)->create();

        SportActivity::factory()->create([
            'user_id' => $user->id,
            'integration_connection_id' => $connection->id,
            'map_polyline' => '_p~iF~ps|U_ulLnnqC_mqNvxq`@',
        ]);

        app(RebuildUserCoverage::class)->handle($user->id);
        app(RebuildUserCoverage::class)->handle($user->id);

        $this->assertSame(3, ExploredCell::query()->where('user_id', $user->id)->count());
    }

    #[Test]
    public function it_ignores_activities_without_a_polyline(): void
    {
        $user = User::factory()->create();
        $connection = IntegrationConnection::factory()->for($user)->create();

        SportActivity::factory()->create([
            'user_id' => $user->id,
            'integration_connection_id' => $connection->id,
            'map_polyline' => null,
        ]);

        $count = app(RebuildUserCoverage::class)->handle($user->id);

        $this->assertSame(0, $count);
        $this->assertSame(0, ExploredCell::query()->where('user_id', $user->id)->count());
    }
}

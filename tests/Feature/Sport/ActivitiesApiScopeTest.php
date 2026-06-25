<?php

namespace Tests\Feature\Sport;

use Functional\Sport\Models\SportActivity;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Technical\Integrations\Models\IntegrationConnection;
use Tests\TestCase;

class ActivitiesApiScopeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_only_returns_the_authenticated_users_activities(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $ownConnection = IntegrationConnection::factory()->for($user)->create();
        $otherConnection = IntegrationConnection::factory()->for($other)->create();

        $ownActivities = SportActivity::factory()->count(2)->create([
            'user_id' => $user->id,
            'integration_connection_id' => $ownConnection->id,
        ]);

        SportActivity::factory()->count(3)->create([
            'user_id' => $other->id,
            'integration_connection_id' => $otherConnection->id,
        ]);

        $response = $this->actingAs($user, 'api')->postJson('/api/sport-activities/search', [
            'search' => [],
        ]);

        $response->assertOk();

        $returnedIds = collect($response->json('data'))->pluck('id')->sort()->values()->all();

        $this->assertCount(2, $response->json('data'));
        $this->assertSame($ownActivities->pluck('id')->sort()->values()->all(), $returnedIds);
    }
}

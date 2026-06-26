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

    #[Test]
    public function it_forbids_updating_another_users_activity(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $connection = IntegrationConnection::factory()->for($other)->create();
        $activity = SportActivity::factory()->create([
            'user_id' => $other->id,
            'integration_connection_id' => $connection->id,
            'name' => 'Original',
        ]);

        $response = $this->actingAs($user, 'api')->postJson('/api/sport-activities/mutate', [
            'mutate' => [
                ['operation' => 'update', 'key' => $activity->id, 'attributes' => ['name' => 'Hijacked']],
            ],
        ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('sport_activities', [
            'id' => $activity->id,
            'name' => 'Original',
        ]);
    }

    #[Test]
    public function it_allows_the_owner_to_update_their_activity(): void
    {
        $user = User::factory()->create();

        $connection = IntegrationConnection::factory()->for($user)->create();
        $activity = SportActivity::factory()->create([
            'user_id' => $user->id,
            'integration_connection_id' => $connection->id,
            'name' => 'Original',
        ]);

        $this->actingAs($user, 'api')->postJson('/api/sport-activities/mutate', [
            'mutate' => [
                ['operation' => 'update', 'key' => $activity->id, 'attributes' => ['name' => 'Updated']],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('sport_activities', ['id' => $activity->id, 'name' => 'Updated']);
    }
}

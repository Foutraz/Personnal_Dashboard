<?php

namespace Tests\Feature\Lomkit;

use Functional\Sport\Models\SportActivity;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Technical\Integrations\Models\IntegrationConnection;
use Tests\TestCase;

class PreventsApiCreationTest extends TestCase
{
    use RefreshDatabase;

    private function assertCreateRejected(string $endpoint): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson($endpoint, [
            'mutate' => [
                ['operation' => 'create', 'attributes' => []],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('mutate');
    }

    #[Test]
    public function it_rejects_creating_a_sport_activity_through_the_api(): void
    {
        $this->assertCreateRejected('/api/sport-activities/mutate');
    }

    #[Test]
    public function it_rejects_creating_a_trip_route_through_the_api(): void
    {
        $this->assertCreateRejected('/api/trip-routes/mutate');
    }

    #[Test]
    public function it_rejects_creating_a_user_through_the_api(): void
    {
        $this->assertCreateRejected('/api/users/mutate');
    }

    #[Test]
    public function it_still_allows_updating_a_guarded_resource(): void
    {
        $user = User::factory()->create();
        $connection = IntegrationConnection::factory()->for($user)->create();
        $activity = SportActivity::factory()->create([
            'user_id' => $user->id,
            'integration_connection_id' => $connection->id,
            'name' => 'Original',
        ]);

        $response = $this->actingAs($user, 'api')->postJson('/api/sport-activities/mutate', [
            'mutate' => [
                ['operation' => 'update', 'key' => $activity->id, 'attributes' => ['name' => 'Renamed']],
            ],
        ]);

        $response->assertSuccessful();
        $this->assertDatabaseHas('sport_activities', ['id' => $activity->id, 'name' => 'Renamed']);
    }
}

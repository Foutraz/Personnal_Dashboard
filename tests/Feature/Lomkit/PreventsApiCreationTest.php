<?php

namespace Tests\Feature\Lomkit;

use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
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
}

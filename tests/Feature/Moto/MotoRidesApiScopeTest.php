<?php

namespace Tests\Feature\Moto;

use Functional\Moto\Models\MotoRide;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MotoRidesApiScopeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_only_returns_the_authenticated_users_rides(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $own = MotoRide::factory()->count(2)->create(['user_id' => $user->id]);
        MotoRide::factory()->count(3)->create(['user_id' => $other->id]);

        $response = $this->actingAs($user, 'api')->postJson('/api/moto-rides/search', [
            'search' => [],
        ]);

        $response->assertOk();

        $returnedIds = collect($response->json('data'))->pluck('id')->sort()->values()->all();

        $this->assertCount(2, $response->json('data'));
        $this->assertSame($own->pluck('id')->sort()->values()->all(), $returnedIds);
    }

    #[Test]
    public function it_assigns_the_authenticated_user_when_creating_a_ride(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->postJson('/api/moto-rides/mutate', [
            'mutate' => [
                [
                    'operation' => 'create',
                    'attributes' => [
                        'title' => 'Balade matinale',
                        'started_at' => now()->subHours(2)->toDateTimeString(),
                        'duration' => 5400,
                        'distance' => 95.4,
                    ],
                ],
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('moto_rides', [
            'title' => 'Balade matinale',
            'user_id' => $user->id,
        ]);
    }

    #[Test]
    public function it_forbids_deleting_another_users_ride(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $ride = MotoRide::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'api')->deleteJson('/api/moto-rides', [
            'resources' => [$ride->id],
        ]);

        $this->assertDatabaseHas('moto_rides', [
            'id' => $ride->id,
            'deleted_at' => null,
        ]);
    }
}

<?php

namespace Tests\Feature\Exploration;

use Functional\Sport\Models\SportActivity;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Technical\Integrations\Models\IntegrationConnection;
use Tests\TestCase;

class TripRoutesApiScopeTest extends TestCase
{
    use RefreshDatabase;

    private function createActivityOf(User $user): SportActivity
    {
        $connection = IntegrationConnection::factory()->for($user)->create();

        return SportActivity::factory()->create([
            'user_id' => $user->id,
            'integration_connection_id' => $connection->id,
            'distance' => 10000,
            'map_polyline' => '_p~iF~ps|U_ulLnnqC_mqNvxq`@',
        ]);
    }

    private function updateTripRoute(User $user, SportActivity $activity, array $attributes): TestResponse
    {
        return $this->actingAs($user, 'api')->postJson('/api/trip-routes/mutate', [
            'mutate' => [[
                'operation' => 'update',
                'key' => $activity->id,
                'attributes' => $attributes,
            ]],
        ]);
    }

    public static function tripRouteFieldValues(): array
    {
        $cases = [];

        $forgedValues = [
            'id' => (string) Str::ulid(),
            'name' => 'Sortie forgée',
            'sport_type' => 'Ride',
            'distance' => 5000000,
            'map_polyline' => 'forged-polyline',
            'started_at' => '2026-01-01 08:00:00',
        ];

        foreach ($forgedValues as $field => $forgedValue) {
            $cases["{$field} filled"] = [$field, $forgedValue];
            $cases["{$field} null"] = [$field, null];
            $cases["{$field} empty string"] = [$field, ''];
        }

        return $cases;
    }

    #[Test]
    #[DataProvider('tripRouteFieldValues')]
    public function it_refuses_the_owner_changing_an_activity_through_the_trip_routes_endpoint(string $field, int|string|null $suppliedValue): void
    {
        $user = User::factory()->create();
        $activity = $this->createActivityOf($user);
        $storedBefore = SportActivity::query()->sole()->getAttributes();

        $response = $this->updateTripRoute($user, $activity, [$field => $suppliedValue]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(["mutate.0.attributes.{$field}"]);
        $this->assertSame($storedBefore, SportActivity::query()->sole()->getAttributes());
    }

    #[Test]
    public function it_does_not_route_the_deletion_of_a_trip_route(): void
    {
        $user = User::factory()->create();
        $activity = $this->createActivityOf($user);

        $this->actingAs($user, 'api')->deleteJson('/api/trip-routes', [
            'resources' => [$activity->id],
        ])->assertMethodNotAllowed();

        $this->assertNotSoftDeleted($activity);
    }

    #[Test]
    public function it_still_searches_the_trip_routes_of_the_authenticated_user_only(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $activity = $this->createActivityOf($user);
        $this->createActivityOf($other);

        $response = $this->actingAs($user, 'api')->postJson('/api/trip-routes/search', [
            'search' => [],
        ]);

        $response->assertOk();
        $this->assertSame([$activity->id], collect($response->json('data'))->pluck('id')->all());
    }
}

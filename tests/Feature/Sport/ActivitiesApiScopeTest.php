<?php

namespace Tests\Feature\Sport;

use Functional\Sport\Enums\SportType;
use Functional\Sport\Models\SportActivity;
use Functional\Sport\Rest\Resource\SportActivityResource;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Http\Requests\RestRequest;
use PHPUnit\Framework\Attributes\DataProvider;
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

    private function updateActivity(User $user, SportActivity $activity, array $attributes): TestResponse
    {
        return $this->actingAs($user, 'api')->postJson('/api/sport-activities/mutate', [
            'mutate' => [[
                'operation' => 'update',
                'key' => $activity->id,
                'attributes' => $attributes,
            ]],
        ]);
    }

    public static function stravaSyncedFieldValues(): array
    {
        $cases = [];

        $forgedValues = [
            'id' => (string) Str::ulid(),
            'strava_id' => 42,
            'distance' => 5000000,
            'moving_time' => 1,
            'elapsed_time' => 1,
            'total_elevation_gain' => 90000,
            'average_speed' => 99,
            'max_speed' => 99,
            'average_heartrate' => 99,
            'max_heartrate' => 99,
            'kilojoules' => 99999,
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
    #[DataProvider('stravaSyncedFieldValues')]
    public function it_rejects_the_owner_changing_a_strava_synced_field(string $field, int|string|null $suppliedValue): void
    {
        $user = User::factory()->create();
        $connection = IntegrationConnection::factory()->for($user)->create();
        $activity = SportActivity::factory()->create([
            'user_id' => $user->id,
            'integration_connection_id' => $connection->id,
            'distance' => 10000,
        ]);
        $storedBefore = SportActivity::query()->sole()->getAttributes();

        $response = $this->updateActivity($user, $activity, [$field => $suppliedValue]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(["mutate.0.attributes.{$field}"]);
        $this->assertSame($storedBefore, SportActivity::query()->sole()->getAttributes());
    }

    #[Test]
    public function it_lets_the_owner_rename_and_retype_their_activity(): void
    {
        $user = User::factory()->create();
        $connection = IntegrationConnection::factory()->for($user)->create();
        $activity = SportActivity::factory()->create([
            'user_id' => $user->id,
            'integration_connection_id' => $connection->id,
            'name' => 'Original',
            'sport_type' => SportType::Run,
        ]);

        $this->updateActivity($user, $activity, [
            'name' => 'Sortie corrigée',
            'sport_type' => SportType::Ride->value,
        ])->assertOk();

        $stored = SportActivity::query()->sole();
        $this->assertSame('Sortie corrigée', $stored->name);
        $this->assertSame(SportType::Ride, $stored->sport_type);
    }

    #[Test]
    public function it_gives_server_managed_rules_only_to_the_fields_the_resource_declares(): void
    {
        $rules = app(SportActivityResource::class)->rules(new RestRequest);

        $this->assertSame(['missing'], $rules['id']);
        $this->assertArrayNotHasKey('created_at', $rules);
        $this->assertArrayNotHasKey('updated_at', $rules);
    }
}

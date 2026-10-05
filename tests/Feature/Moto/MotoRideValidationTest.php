<?php

namespace Tests\Feature\Moto;

use Functional\Moto\Models\MotoRide;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MotoRideValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-01 10:00:00', 'UTC'));
        $this->owner = User::factory()->create();
    }

    private function createRide(array $attributes): TestResponse
    {
        return $this->actingAs($this->owner, 'api')->postJson('/api/moto-rides/mutate', [
            'mutate' => [[
                'operation' => 'create',
                'attributes' => [
                    'title' => 'Balade',
                    'started_at' => '2026-10-01 08:00:00',
                    'duration' => 3600,
                    'distance' => 80,
                    ...$attributes,
                ],
            ]],
        ]);
    }

    private function updateRide(MotoRide $ride, array $attributes): TestResponse
    {
        return $this->actingAs($this->owner, 'api')->postJson('/api/moto-rides/mutate', [
            'mutate' => [[
                'operation' => 'update',
                'key' => $ride->id,
                'attributes' => $attributes,
            ]],
        ]);
    }

    #[Test]
    public function it_accepts_a_ride_started_at_the_current_instant(): void
    {
        $this->createRide(['started_at' => '2026-10-01 10:00:00'])->assertOk();

        $this->assertSame(1, MotoRide::query()->count());
    }

    #[Test]
    public function it_rejects_a_ride_started_one_second_in_the_future(): void
    {
        $response = $this->createRide(['started_at' => '2026-10-01 10:00:01']);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['mutate.0.attributes.started_at']);
        $this->assertSame(0, MotoRide::query()->count());
    }

    #[Test]
    public function it_rejects_a_ride_started_at_the_epoch(): void
    {
        $response = $this->createRide(['started_at' => '1970-01-01 00:00:00']);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['mutate.0.attributes.started_at']);
        $this->assertSame(0, MotoRide::query()->count());
    }

    #[Test]
    public function it_rejects_moving_an_own_ride_into_the_future(): void
    {
        $ride = MotoRide::factory()->for($this->owner)->create([
            'started_at' => Carbon::parse('2026-09-20 08:00:00', 'UTC'),
        ]);

        $response = $this->updateRide($ride, ['started_at' => '2026-10-02 08:00:00']);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['mutate.0.attributes.started_at']);
        $this->assertTrue($ride->fresh()->started_at->equalTo(Carbon::parse('2026-09-20 08:00:00', 'UTC')));
    }

    public static function acceptedDistances(): array
    {
        return [
            'maximum' => [2000],
            'smallest positive' => [0.01],
        ];
    }

    public static function rejectedDistances(): array
    {
        return [
            'zero' => [0],
            'above the maximum' => [2000.01],
        ];
    }

    public static function acceptedDurations(): array
    {
        return [
            'minimum' => [60],
            'maximum' => [86400],
        ];
    }

    public static function rejectedDurations(): array
    {
        return [
            'below the minimum' => [59],
            'above the maximum' => [86401],
        ];
    }

    #[Test]
    #[DataProvider('acceptedDistances')]
    public function it_accepts_a_distance_within_bounds(float|int $distance): void
    {
        $this->createRide(['distance' => $distance])->assertOk();

        $this->assertSame(1, MotoRide::query()->count());
    }

    #[Test]
    #[DataProvider('rejectedDistances')]
    public function it_rejects_a_distance_out_of_bounds(float|int $distance): void
    {
        $response = $this->createRide(['distance' => $distance]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['mutate.0.attributes.distance']);
        $this->assertSame(0, MotoRide::query()->count());
    }

    #[Test]
    #[DataProvider('acceptedDurations')]
    public function it_accepts_a_duration_within_bounds(int $duration): void
    {
        $this->createRide(['duration' => $duration])->assertOk();

        $this->assertSame(1, MotoRide::query()->count());
    }

    #[Test]
    #[DataProvider('rejectedDurations')]
    public function it_rejects_a_duration_out_of_bounds(int $duration): void
    {
        $response = $this->createRide(['duration' => $duration]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['mutate.0.attributes.duration']);
        $this->assertSame(0, MotoRide::query()->count());
    }

    #[Test]
    public function it_rejects_a_client_chosen_creation_timestamp(): void
    {
        $response = $this->createRide(['created_at' => '2026-09-01 08:00:00']);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['mutate.0.attributes.created_at']);
        $this->assertSame(0, MotoRide::query()->count());
    }

    #[Test]
    public function it_rejects_a_client_chosen_key(): void
    {
        $response = $this->createRide(['id' => (new MotoRide)->newUniqueId()]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['mutate.0.attributes.id']);
        $this->assertSame(0, MotoRide::query()->count());
    }

    #[Test]
    public function it_rejects_a_client_chosen_update_timestamp(): void
    {
        $ride = MotoRide::factory()->for($this->owner)->create([
            'started_at' => Carbon::parse('2026-09-20 08:00:00', 'UTC'),
        ]);
        $originalUpdatedAt = $ride->updated_at;

        $response = $this->updateRide($ride, ['updated_at' => '2026-09-25 08:00:00']);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['mutate.0.attributes.updated_at']);
        $this->assertTrue($ride->fresh()->updated_at->equalTo($originalUpdatedAt));
    }

    #[Test]
    public function it_stamps_the_server_instant_on_a_ride_dated_in_the_past(): void
    {
        $this->createRide(['started_at' => '2026-09-14 08:00:00'])->assertOk();

        $ride = MotoRide::query()->sole();
        $this->assertTrue($ride->started_at->equalTo(Carbon::parse('2026-09-14 08:00:00', 'UTC')));
        $this->assertTrue($ride->created_at->equalTo(Carbon::parse('2026-10-01 10:00:00', 'UTC')));
    }

    #[Test]
    public function it_aligns_the_factory_creation_timestamps_on_the_start_of_the_ride(): void
    {
        $ride = MotoRide::factory()->create([
            'started_at' => Carbon::parse('2026-09-02 12:00:00', 'UTC'),
        ]);

        $this->assertTrue($ride->created_at->equalTo(Carbon::parse('2026-09-02 12:00:00', 'UTC')));
        $this->assertTrue($ride->updated_at->equalTo(Carbon::parse('2026-09-02 12:00:00', 'UTC')));
    }
}

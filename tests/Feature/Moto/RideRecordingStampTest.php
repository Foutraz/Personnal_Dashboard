<?php

namespace Tests\Feature\Moto;

use Functional\Moto\Listeners\StampRideRecording;
use Functional\Moto\Livewire\MotoDashboard;
use Functional\Moto\Models\MotoRide;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RideRecordingStampTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-01 10:00:00', 'UTC'));
        $this->owner = User::factory()->create();
    }

    private function createRide(array $attributes = []): TestResponse
    {
        return $this->actingAs($this->owner, 'api')->postJson('/api/moto-rides/mutate', [
            'mutate' => [[
                'operation' => 'create',
                'attributes' => [
                    'title' => 'Balade',
                    'started_at' => '2026-09-14 08:00:00',
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

    private function ownRide(): MotoRide
    {
        return MotoRide::factory()->for($this->owner)->create([
            'started_at' => Carbon::parse('2026-09-20 08:00:00', 'UTC'),
            'distance' => 80,
        ]);
    }

    private function assertRecordedAt(string $instant, MotoRide $ride): void
    {
        $this->assertTrue(
            $ride->fresh()->recorded_at->equalTo(Carbon::parse($instant, 'UTC')),
            "Expected recorded_at {$instant}, got {$ride->fresh()->recorded_at->toDateTimeString()}",
        );
    }

    #[Test]
    public function it_stamps_the_server_instant_on_a_ride_created_through_the_api(): void
    {
        $this->createRide(['started_at' => '2026-09-14 08:00:00'])->assertOk();

        $this->assertRecordedAt('2026-10-01 10:00:00', MotoRide::query()->sole());
    }

    #[Test]
    public function it_stamps_the_server_instant_on_a_ride_logged_from_the_dashboard(): void
    {
        Config::set('weather.api_key', null);

        Livewire::actingAs($this->owner, 'web')
            ->test(MotoDashboard::class)
            ->set('rideTitle', 'Sortie')
            ->set('rideStartedAt', '2026-09-14T08:00')
            ->set('rideDuration', '90')
            ->set('rideDistance', '120')
            ->call('logRide')
            ->assertHasNoErrors();

        $this->assertRecordedAt('2026-10-01 10:00:00', MotoRide::query()->sole());
    }

    #[Test]
    public function it_stamps_the_server_instant_on_a_ride_created_through_eloquent(): void
    {
        $ride = MotoRide::query()->create([
            'user_id' => $this->owner->id,
            'title' => 'Balade',
            'started_at' => Carbon::parse('2026-09-14 08:00:00', 'UTC'),
            'duration' => 3600,
            'distance' => 80,
        ]);

        $this->assertRecordedAt('2026-10-01 10:00:00', $ride);
    }

    #[Test]
    public function it_ignores_a_recording_instant_mass_assigned_on_creation(): void
    {
        $ride = MotoRide::query()->create([
            'user_id' => $this->owner->id,
            'title' => 'Balade',
            'started_at' => Carbon::parse('2026-09-14 08:00:00', 'UTC'),
            'duration' => 3600,
            'distance' => 80,
            'recorded_at' => Carbon::parse('2026-09-14 09:00:00', 'UTC'),
        ]);

        $this->assertRecordedAt('2026-10-01 10:00:00', $ride);
    }

    #[Test]
    public function it_stamps_the_server_instant_on_a_replica_of_an_old_ride(): void
    {
        $ride = $this->ownRide();
        $this->travelTo(Carbon::parse('2026-10-02 08:00:00', 'UTC'));

        $replica = $ride->replicate();
        $replica->save();

        $this->assertRecordedAt('2026-10-02 08:00:00', $replica);
        $this->assertRecordedAt('2026-09-20 08:00:00', $ride);
    }

    #[Test]
    public function it_overrides_a_recording_instant_assigned_to_a_new_ride(): void
    {
        $ride = new MotoRide;
        $ride->forceFill([
            'user_id' => $this->owner->id,
            'title' => 'Balade',
            'started_at' => Carbon::parse('2026-09-14 08:00:00', 'UTC'),
            'duration' => 3600,
            'distance' => 80,
            'recorded_at' => Carbon::parse('2026-09-14 09:00:00', 'UTC'),
        ])->save();

        $this->assertRecordedAt('2026-10-01 10:00:00', $ride);
    }

    #[Test]
    public function it_keeps_the_recording_when_only_the_title_changes(): void
    {
        $ride = $this->ownRide();
        $this->travelTo(Carbon::parse('2026-10-02 08:00:00', 'UTC'));

        $this->updateRide($ride, ['title' => 'Renamed'])->assertOk();

        $this->assertSame('Renamed', $ride->fresh()->title);
        $this->assertRecordedAt('2026-09-20 08:00:00', $ride);
    }

    #[Test]
    public function it_keeps_the_recording_when_the_same_start_and_distance_are_resent(): void
    {
        $ride = $this->ownRide();
        $this->travelTo(Carbon::parse('2026-10-02 08:00:00', 'UTC'));

        $this->updateRide($ride, ['started_at' => '2026-09-20 08:00:00', 'distance' => 80])->assertOk();

        $this->assertRecordedAt('2026-09-20 08:00:00', $ride);
    }

    #[Test]
    public function it_restamps_the_server_instant_when_the_start_changes(): void
    {
        $ride = $this->ownRide();
        $this->travelTo(Carbon::parse('2026-10-02 08:00:00', 'UTC'));

        $this->updateRide($ride, ['started_at' => '2026-09-21 08:00:00'])->assertOk();

        $this->assertRecordedAt('2026-10-02 08:00:00', $ride);
    }

    #[Test]
    public function it_restamps_the_server_instant_when_the_distance_changes(): void
    {
        $ride = $this->ownRide();
        $this->travelTo(Carbon::parse('2026-10-02 08:00:00', 'UTC'));

        $this->updateRide($ride, ['distance' => 120])->assertOk();

        $this->assertRecordedAt('2026-10-02 08:00:00', $ride);
    }

    #[Test]
    public function it_restamps_the_server_instant_when_an_eloquent_update_changes_the_start(): void
    {
        $ride = $this->ownRide();
        $this->travelTo(Carbon::parse('2026-10-02 08:00:00', 'UTC'));

        $ride->update(['started_at' => Carbon::parse('2026-09-21 08:00:00', 'UTC')]);

        $this->assertRecordedAt('2026-10-02 08:00:00', $ride);
    }

    #[Test]
    public function it_keeps_the_recording_when_a_trashed_ride_is_restored(): void
    {
        $ride = $this->ownRide();
        $ride->delete();
        $this->travelTo(Carbon::parse('2026-10-02 08:00:00', 'UTC'));

        $ride->restore();

        $this->assertRecordedAt('2026-09-20 08:00:00', $ride);
    }

    public static function clientChosenRecordings(): array
    {
        return [
            'an instant' => ['2026-09-01 08:00:00'],
            'null' => [null],
            'an empty string' => [''],
        ];
    }

    #[Test]
    #[DataProvider('clientChosenRecordings')]
    public function it_rejects_a_client_chosen_recording_instant_on_creation(?string $recordedAt): void
    {
        $response = $this->createRide(['recorded_at' => $recordedAt]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['mutate.0.attributes']);
        $this->assertSame(0, MotoRide::query()->count());
    }

    #[Test]
    #[DataProvider('clientChosenRecordings')]
    public function it_rejects_a_client_chosen_recording_instant_on_update(?string $recordedAt): void
    {
        $ride = $this->ownRide();
        $storedBefore = MotoRide::query()->sole()->getAttributes();

        $response = $this->updateRide($ride, ['started_at' => '2026-09-21 08:00:00', 'recorded_at' => $recordedAt]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['mutate.0.attributes']);
        $this->assertSame($storedBefore, MotoRide::query()->sole()->getAttributes());
    }

    #[Test]
    public function it_exposes_no_recording_instant_through_the_api_fields(): void
    {
        $this->ownRide();

        $response = $this->actingAs($this->owner, 'api')->postJson('/api/moto-rides/search', [
            'search' => [],
        ]);

        $response->assertOk();
        $this->assertArrayNotHasKey('recorded_at', $response->json('data.0'));
    }

    #[Test]
    public function it_aligns_the_factory_recording_instant_on_the_start_of_the_ride(): void
    {
        $ride = MotoRide::factory()->create([
            'started_at' => Carbon::parse('2026-09-02 12:00:00', 'UTC'),
        ]);

        $this->assertRecordedAt('2026-09-02 12:00:00', $ride);
    }

    #[Test]
    public function it_keeps_a_recording_instant_set_explicitly_by_the_factory(): void
    {
        $ride = MotoRide::factory()->create([
            'started_at' => Carbon::parse('2026-09-02 12:00:00', 'UTC'),
            'recorded_at' => Carbon::parse('2026-09-30 12:00:00', 'UTC'),
        ]);

        $this->assertRecordedAt('2026-09-30 12:00:00', $ride);
    }

    #[Test]
    public function it_hands_back_a_factory_ride_carrying_its_aligned_recording_instant(): void
    {
        $ride = MotoRide::factory()->create([
            'started_at' => Carbon::parse('2026-09-02 12:00:00', 'UTC'),
        ]);

        $this->assertTrue($ride->recorded_at->equalTo(Carbon::parse('2026-09-02 12:00:00', 'UTC')));
        $this->assertFalse($ride->isDirty());
    }

    #[Test]
    public function it_aligns_the_recording_instant_of_a_trashed_factory_ride(): void
    {
        $ride = MotoRide::factory()->create([
            'started_at' => Carbon::parse('2026-09-02 12:00:00', 'UTC'),
            'deleted_at' => Carbon::parse('2026-09-03 12:00:00', 'UTC'),
        ]);

        $this->assertTrue(MotoRide::withTrashed()->findOrFail($ride->id)->recorded_at->equalTo(Carbon::parse('2026-09-02 12:00:00', 'UTC')));
    }

    #[Test]
    public function it_leaves_the_update_timestamp_of_a_factory_ride_on_its_start(): void
    {
        $ride = MotoRide::factory()->create([
            'started_at' => Carbon::parse('2026-09-02 12:00:00', 'UTC'),
        ]);

        $this->assertTrue($ride->fresh()->updated_at->equalTo(Carbon::parse('2026-09-02 12:00:00', 'UTC')));
    }

    #[Test]
    public function it_wires_the_recording_stamp_on_the_saving_event_of_a_ride(): void
    {
        Event::fake();

        Event::assertListening('eloquent.saving: '.MotoRide::class, StampRideRecording::class);
    }
}

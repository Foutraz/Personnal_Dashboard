<?php

namespace Tests\Feature\Moto;

use Functional\Moto\Livewire\MotoDashboard;
use Functional\Moto\Models\MotoRide;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Testing\TestResponse;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RideDateTimezoneTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.display_timezone', 'UTC');
        $this->travelTo(Carbon::parse('2026-10-01 10:00:00', 'UTC'));
        $this->owner = User::factory()->create();
    }

    private function createRide(string|int $startedAt): TestResponse
    {
        return $this->actingAs($this->owner, 'api')->postJson('/api/moto-rides/mutate', [
            'mutate' => [[
                'operation' => 'create',
                'attributes' => [
                    'title' => 'Balade',
                    'started_at' => $startedAt,
                    'duration' => 3600,
                    'distance' => 80,
                ],
            ]],
        ]);
    }

    private function updateRide(MotoRide $ride, string $startedAt): TestResponse
    {
        return $this->actingAs($this->owner, 'api')->postJson('/api/moto-rides/mutate', [
            'mutate' => [[
                'operation' => 'update',
                'key' => $ride->id,
                'attributes' => ['started_at' => $startedAt],
            ]],
        ]);
    }

    private function logRide(string $startedAt): Testable
    {
        Config::set('weather.api_key', null);

        return Livewire::actingAs($this->owner, 'web')
            ->test(MotoDashboard::class)
            ->set('rideTitle', 'Sortie')
            ->set('rideStartedAt', $startedAt)
            ->set('rideDuration', '90')
            ->set('rideDistance', '120')
            ->call('logRide');
    }

    private function storedStartedAt(): string
    {
        return MotoRide::query()->sole()->getRawOriginal('started_at');
    }

    public static function acceptedZonedStarts(): array
    {
        return [
            'positive offset equal to the past in utc' => ['2026-10-01T23:00:00+14:00', '2026-10-01 09:00:00'],
            'negative offset equal to now in utc' => ['2026-10-01T05:00:00-05:00', '2026-10-01 10:00:00'],
            'offset epoch floor in utc' => ['1970-01-01T00:00:00-14:00', '1970-01-01 14:00:00'],
            'zulu marker' => ['2026-10-01T10:00:00Z', '2026-10-01 10:00:00'],
            'no offset read as utc' => ['2026-10-01 08:00:00', '2026-10-01 08:00:00'],
        ];
    }

    public static function rejectedZonedStarts(): array
    {
        return [
            'positive offset one second after now in utc' => ['2026-10-02T00:00:01+14:00'],
            'negative offset one second after now in utc' => ['2026-10-01T05:00:01-05:00'],
            'offset equal to the epoch in utc' => ['1970-01-01T14:00:00+14:00'],
        ];
    }

    public static function malformedStarts(): array
    {
        return [
            'digits read as a day of the year' => ['01800041970'],
            'digits read as a day month year' => ['001812081982'],
            'digits read as a date and minutes' => ['202609301010'],
            'compact date' => ['20260930'],
            'unix timestamp marker' => ['@1790845200'],
            'relative word' => ['yesterday'],
            'zone identifier' => ['2026-10-01 23:00:00 Pacific/Kiritimati'],
            'zone identifier after now in utc' => ['2026-10-02 00:00:01 Pacific/Kiritimati'],
        ];
    }

    public static function localStartsOneMinuteBeforeNow(): array
    {
        return [
            'paris ahead of utc' => ['Europe/Paris', '2026-10-01T11:59', '2026-10-01 09:59:00'],
            'new york behind utc' => ['America/New_York', '2026-10-01T05:59', '2026-10-01 09:59:00'],
        ];
    }

    public static function localStartsOneMinuteAfterNow(): array
    {
        return [
            'paris ahead of utc' => ['Europe/Paris', '2026-10-01T12:01'],
            'new york behind utc' => ['America/New_York', '2026-10-01T06:01'],
        ];
    }

    public static function parisLocalStartsNotAfterTheEpochInUtc(): array
    {
        return [
            'half an hour before the epoch in utc' => ['1970-01-01T00:30'],
            'equal to the epoch in utc' => ['1970-01-01T01:00'],
        ];
    }

    #[Test]
    #[DataProvider('acceptedZonedStarts')]
    public function it_stores_the_utc_wall_clock_of_a_zoned_start_created_through_the_api(string $startedAt, string $stored): void
    {
        $this->createRide($startedAt)->assertOk();

        $this->assertSame($stored, $this->storedStartedAt());
    }

    #[Test]
    #[DataProvider('rejectedZonedStarts')]
    public function it_rejects_a_zoned_start_after_now_in_utc_through_the_api(string $startedAt): void
    {
        $response = $this->createRide($startedAt);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['mutate.0.attributes.started_at']);
        $this->assertSame(0, MotoRide::query()->count());
    }

    #[Test]
    #[DataProvider('malformedStarts')]
    public function it_rejects_a_malformed_start_through_the_api(string $startedAt): void
    {
        $response = $this->createRide($startedAt);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['mutate.0.attributes.started_at']);
        $this->assertSame(0, MotoRide::query()->count());
    }

    #[Test]
    public function it_rejects_a_start_sent_as_a_json_number_through_the_api(): void
    {
        $response = $this->createRide(20260930);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['mutate.0.attributes.started_at']);
        $this->assertSame(0, MotoRide::query()->count());
    }

    #[Test]
    #[DataProvider('malformedStarts')]
    public function it_rejects_a_malformed_start_through_an_api_update(string $startedAt): void
    {
        $ride = MotoRide::factory()->for($this->owner)->create([
            'started_at' => Carbon::parse('2026-09-20 08:00:00', 'UTC'),
        ]);

        $response = $this->updateRide($ride, $startedAt);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['mutate.0.attributes.started_at']);
        $this->assertSame('2026-09-20 08:00:00', $this->storedStartedAt());
    }

    #[Test]
    public function it_stores_the_utc_wall_clock_of_a_zoned_start_set_through_an_api_update(): void
    {
        $ride = MotoRide::factory()->for($this->owner)->create([
            'started_at' => Carbon::parse('2026-09-20 08:00:00', 'UTC'),
        ]);

        $this->updateRide($ride, '2026-10-01T23:00:00+14:00')->assertOk();

        $this->assertSame('2026-10-01 09:00:00', $this->storedStartedAt());
    }

    #[Test]
    public function it_rejects_a_zoned_start_after_now_in_utc_through_an_api_update(): void
    {
        $ride = MotoRide::factory()->for($this->owner)->create([
            'started_at' => Carbon::parse('2026-09-20 08:00:00', 'UTC'),
        ]);

        $response = $this->updateRide($ride, '2026-10-02T00:00:01+14:00');

        $response->assertUnprocessable();
        $this->assertSame('2026-09-20 08:00:00', $this->storedStartedAt());
    }

    #[Test]
    #[DataProvider('malformedStarts')]
    public function it_rejects_a_malformed_start_logged_from_the_dashboard(string $startedAt): void
    {
        $this->logRide($startedAt)->assertHasErrors(['rideStartedAt']);

        $this->assertSame(0, MotoRide::query()->count());
    }

    #[Test]
    public function it_returns_the_stored_start_as_the_same_utc_instant_through_the_api(): void
    {
        $this->createRide('2026-10-01T23:00:00+14:00')->assertOk();

        $response = $this->actingAs($this->owner, 'api')->postJson('/api/moto-rides/search', ['search' => []]);

        $this->assertTrue(
            Carbon::parse($response->json('data.0.started_at'))->equalTo(Carbon::parse('2026-10-01 09:00:00', 'UTC')),
        );
    }

    #[Test]
    #[DataProvider('acceptedZonedStarts')]
    public function it_stores_the_utc_wall_clock_of_a_zoned_start_logged_from_the_dashboard(string $startedAt, string $stored): void
    {
        $this->logRide($startedAt)->assertHasNoErrors();

        $this->assertSame($stored, $this->storedStartedAt());
    }

    #[Test]
    #[DataProvider('rejectedZonedStarts')]
    public function it_rejects_a_zoned_start_after_now_in_utc_logged_from_the_dashboard(string $startedAt): void
    {
        $this->logRide($startedAt)->assertHasErrors(['rideStartedAt']);

        $this->assertSame(0, MotoRide::query()->count());
    }

    #[Test]
    #[DataProvider('localStartsOneMinuteBeforeNow')]
    public function it_stores_a_local_start_one_minute_before_now_as_its_utc_instant(string $displayTimezone, string $localStart, string $stored): void
    {
        Config::set('app.display_timezone', $displayTimezone);

        $this->logRide($localStart)->assertHasNoErrors();

        $this->assertSame($stored, $this->storedStartedAt());
    }

    #[Test]
    #[DataProvider('localStartsOneMinuteAfterNow')]
    public function it_rejects_a_local_start_one_minute_after_now_on_the_start_field(string $displayTimezone, string $localStart): void
    {
        Config::set('app.display_timezone', $displayTimezone);

        $this->logRide($localStart)->assertHasErrors(['rideStartedAt' => 'before_or_equal']);

        $this->assertSame(0, MotoRide::query()->count());
    }

    #[Test]
    #[DataProvider('parisLocalStartsNotAfterTheEpochInUtc')]
    public function it_rejects_a_paris_local_start_not_after_the_epoch_in_utc(string $localStart): void
    {
        Config::set('app.display_timezone', 'Europe/Paris');

        $this->logRide($localStart)->assertHasErrors(['rideStartedAt' => 'after']);

        $this->assertSame(0, MotoRide::query()->count());
    }

    #[Test]
    public function it_stores_a_paris_local_start_just_after_the_epoch_in_utc(): void
    {
        Config::set('app.display_timezone', 'Europe/Paris');

        $this->logRide('1970-01-01T01:30')->assertHasNoErrors();

        $this->assertSame('1970-01-01 00:30:00', $this->storedStartedAt());
    }
}

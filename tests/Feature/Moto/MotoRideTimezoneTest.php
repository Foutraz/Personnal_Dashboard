<?php

namespace Tests\Feature\Moto;

use Carbon\CarbonImmutable;
use Functional\Moto\Livewire\MotoDashboard;
use Functional\Moto\Models\MotoRide;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Technical\Application\Exceptions\InvalidDisplayTimezoneException;
use Technical\Application\Time\DisplayTimezone;
use Tests\TestCase;

class MotoRideTimezoneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('weather.api_key', null);
        $this->travelTo(CarbonImmutable::parse('2026-10-04 21:30:00', 'UTC'));
    }

    #[Test]
    public function it_prefills_the_start_with_the_current_local_time(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user, 'web')
            ->test(MotoDashboard::class)
            ->assertSet('rideStartedAt', '2026-10-04T23:30');
    }

    #[Test]
    public function it_stores_a_summer_time_local_start_as_the_utc_instant(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user, 'web')
            ->test(MotoDashboard::class)
            ->set('rideTitle', 'Sortie dominicale')
            ->set('rideStartedAt', '2026-10-04T23:30')
            ->set('rideDuration', '90')
            ->set('rideDistance', '120')
            ->call('logRide')
            ->assertHasNoErrors()
            ->assertSet('rideStartedAt', '2026-10-04T23:30');

        $this->assertSame('2026-10-04 21:30:00', MotoRide::query()->sole()->started_at->toDateTimeString());
    }

    #[Test]
    public function it_stores_a_past_winter_time_local_start_as_the_utc_instant(): void
    {
        Config::set('app.display_timezone', 'Europe/Paris');
        $user = User::factory()->create();

        Livewire::actingAs($user, 'web')
            ->test(MotoDashboard::class)
            ->set('rideTitle', 'Sortie hivernale')
            ->set('rideStartedAt', '2026-01-15T08:00')
            ->set('rideDuration', '90')
            ->set('rideDistance', '120')
            ->call('logRide')
            ->assertHasNoErrors();

        $this->assertSame('2026-01-15 07:00:00', MotoRide::query()->sole()->started_at->toDateTimeString());
    }

    #[Test]
    public function it_resets_the_start_to_the_current_local_minute_in_a_display_timezone_behind_utc(): void
    {
        Config::set('app.display_timezone', 'America/New_York');
        $user = User::factory()->create();

        Livewire::actingAs($user, 'web')
            ->test(MotoDashboard::class)
            ->set('rideTitle', 'Sortie du soir')
            ->set('rideStartedAt', '2026-10-04T16:00')
            ->set('rideDuration', '90')
            ->set('rideDistance', '120')
            ->call('logRide')
            ->assertHasNoErrors()
            ->assertSet('rideStartedAt', CarbonImmutable::now('America/New_York')->format('Y-m-d\TH:i'));

        $this->assertSame('2026-10-04 20:00:00', MotoRide::query()->sole()->started_at->toDateTimeString());
    }

    #[Test]
    public function it_rejects_a_winter_time_local_start_in_the_future(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user, 'web')
            ->test(MotoDashboard::class)
            ->set('rideTitle', 'Sortie dominicale')
            ->set('rideStartedAt', '2026-12-06T23:30')
            ->set('rideDuration', '90')
            ->set('rideDistance', '120')
            ->call('logRide')
            ->assertHasErrors(['rideStartedAt' => 'before_or_equal']);

        $this->assertSame(0, MotoRide::query()->count());
    }

    #[Test]
    public function it_rejects_a_local_start_in_the_future_in_a_display_timezone_behind_utc(): void
    {
        Config::set('app.display_timezone', 'America/New_York');
        $user = User::factory()->create();

        Livewire::actingAs($user, 'web')
            ->test(MotoDashboard::class)
            ->assertSet('rideStartedAt', '2026-10-04T17:30')
            ->set('rideTitle', 'Sortie dominicale')
            ->set('rideStartedAt', '2026-10-04T23:30')
            ->set('rideDuration', '90')
            ->set('rideDistance', '120')
            ->call('logRide')
            ->assertHasErrors(['rideStartedAt' => 'before_or_equal']);

        $this->assertSame(0, MotoRide::query()->count());
    }

    #[Test]
    #[DataProvider('displayTimezonesAroundUtc')]
    public function it_stores_a_local_start_one_minute_ago_in_the_past(string $displayTimezone): void
    {
        Config::set('app.display_timezone', $displayTimezone);
        $user = User::factory()->create();
        $localStart = CarbonImmutable::now($displayTimezone)->subMinute()->format('Y-m-d\TH:i');

        Livewire::actingAs($user, 'web')
            ->test(MotoDashboard::class)
            ->set('rideTitle', 'Sortie dominicale')
            ->set('rideStartedAt', $localStart)
            ->set('rideDuration', '90')
            ->set('rideDistance', '120')
            ->call('logRide')
            ->assertHasNoErrors();

        $this->assertTrue(MotoRide::query()->sole()->started_at->isPast());
    }

    #[Test]
    #[DataProvider('displayTimezonesAroundUtc')]
    public function it_rejects_a_local_start_one_minute_from_now(string $displayTimezone): void
    {
        Config::set('app.display_timezone', $displayTimezone);
        $user = User::factory()->create();
        $localStart = CarbonImmutable::now($displayTimezone)->addMinute()->format('Y-m-d\TH:i');

        Livewire::actingAs($user, 'web')
            ->test(MotoDashboard::class)
            ->set('rideTitle', 'Sortie dominicale')
            ->set('rideStartedAt', $localStart)
            ->set('rideDuration', '90')
            ->set('rideDistance', '120')
            ->call('logRide')
            ->assertHasErrors(['rideStartedAt' => 'before_or_equal']);

        $this->assertSame(0, MotoRide::query()->count());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function displayTimezonesAroundUtc(): array
    {
        return [
            'ahead of UTC' => ['Europe/Paris'],
            'behind UTC' => ['America/New_York'],
        ];
    }

    #[Test]
    public function it_lists_the_ride_start_in_the_display_timezone(): void
    {
        $user = User::factory()->create();
        MotoRide::factory()->create([
            'user_id' => $user->id,
            'started_at' => CarbonImmutable::parse('2026-10-04 21:30:00', 'UTC'),
        ]);

        Livewire::actingAs($user, 'web')
            ->test(MotoDashboard::class)
            ->assertSee('04/10/2026 23h')
            ->assertDontSee('04/10/2026 21h');
    }

    #[Test]
    public function it_lists_the_ride_start_across_midnight_in_the_display_timezone(): void
    {
        $user = User::factory()->create();
        MotoRide::factory()->create([
            'user_id' => $user->id,
            'started_at' => CarbonImmutable::parse('2026-10-04 22:30:00', 'UTC'),
        ]);

        Livewire::actingAs($user, 'web')
            ->test(MotoDashboard::class)
            ->assertSee('05/10/2026 00h')
            ->assertDontSee('04/10/2026 22h');
    }

    #[Test]
    public function it_fails_loudly_when_the_display_timezone_is_misconfigured(): void
    {
        Config::set('app.display_timezone', 'Mars/Olympus');

        $this->expectException(InvalidDisplayTimezoneException::class);

        (new MotoDashboard)->mount($this->app->make(DisplayTimezone::class));
    }
}

<?php

namespace Tests\Feature\Moto;

use Carbon\CarbonPeriod;
use DateTimeImmutable;
use Foutraz\Weather\Dto\Forecast;
use Functional\Moto\Dashboard\MotoAgendaProvider;
use Functional\Moto\Enums\RideRating;
use Functional\Moto\Services\FavorableSlotFinder;
use Functional\Moto\Services\WeatherForecastService;
use Functional\Moto\ValueObjects\FavorableSlot;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MotoAgendaProviderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_favorable_slots_within_the_period_when_configured(): void
    {
        $this->bindWeather(true);
        $this->app->bind(FavorableSlotFinder::class, fn (): FavorableSlotFinder => new class extends FavorableSlotFinder
        {
            public function __construct() {}

            public function find(Forecast $forecast): array
            {
                return [
                    new FavorableSlot(new DateTimeImmutable('2026-07-05 14:00'), new DateTimeImmutable('2026-07-05 18:00'), 80, RideRating::Excellent),
                    new FavorableSlot(new DateTimeImmutable('2026-12-05 14:00'), new DateTimeImmutable('2026-12-05 18:00'), 75, RideRating::Good),
                ];
            }
        });

        $items = $this->app->make(MotoAgendaProvider::class)->agendaItems(User::factory()->create(), CarbonPeriod::create('2026-07-01', '2026-07-31'));

        $this->assertCount(1, $items);
        $this->assertSame('moto', $items->first()->source);
        $this->assertSame('lime', $items->first()->accent);
    }

    #[Test]
    public function it_returns_nothing_when_weather_is_not_configured(): void
    {
        $this->bindWeather(false);

        $items = $this->app->make(MotoAgendaProvider::class)->agendaItems(User::factory()->create(), CarbonPeriod::create('2026-07-01', '2026-07-31'));

        $this->assertCount(0, $items);
    }

    private function bindWeather(bool $configured): void
    {
        $this->app->bind(WeatherForecastService::class, fn (): WeatherForecastService => new class($configured) extends WeatherForecastService
        {
            public function __construct(private bool $ready) {}

            public function isConfigured(): bool
            {
                return $this->ready;
            }

            public function forecast(float $lat, float $lon): Forecast
            {
                return new Forecast($lat, $lon, []);
            }
        });
    }
}

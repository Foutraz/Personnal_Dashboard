<?php

namespace Tests\Feature\Moto;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Foutraz\Weather\WeatherManager;
use Functional\Moto\Livewire\MotoDashboard;
use Functional\Users\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MotoWeatherTimezoneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->setLocale('en');
    }

    private function bindClearForecastStartingAt(CarbonImmutable $firstEntry): void
    {
        Config::set('weather.api_key', 'test-key');

        $handler = HandlerStack::create(new MockHandler([
            new Response(200, [], (string) json_encode([
                'dt' => $firstEntry->getTimestamp(),
                'main' => ['temp' => 21.0, 'feels_like' => 21.0, 'humidity' => 55, 'pressure' => 1015],
                'wind' => ['speed' => 2.0, 'gust' => 3.0],
                'clouds' => ['all' => 10],
                'visibility' => 10000,
                'weather' => [['main' => 'Clear', 'description' => 'clear sky']],
                'coord' => ['lat' => 48.85, 'lon' => 2.35],
            ])),
            new Response(200, [], (string) json_encode([
                'city' => ['coord' => ['lat' => 48.85, 'lon' => 2.35]],
                'list' => [[
                    'dt' => $firstEntry->getTimestamp(),
                    'main' => ['temp' => 21.0, 'feels_like' => 21.0],
                    'wind' => ['speed' => 2.0],
                    'pop' => 0.0,
                    'clouds' => ['all' => 5],
                    'visibility' => 10000,
                    'weather' => [['main' => 'Clear', 'description' => 'clear sky']],
                ]],
            ])),
        ]));

        $client = new Client(['handler' => $handler, 'http_errors' => false]);

        $this->app->instance(WeatherManager::class, new WeatherManager('https://api.openweathermap.org', 'test-key', $client));
    }

    #[Test]
    public function it_labels_the_hourly_forecast_in_the_display_timezone(): void
    {
        $this->bindClearForecastStartingAt(CarbonImmutable::parse('2026-10-04 22:00:00', 'UTC'));
        $user = User::factory()->create();

        Livewire::actingAs($user, 'web')
            ->test(MotoDashboard::class)
            ->assertSee('Mon 00h')
            ->assertDontSee('Sun 22h');
    }

    #[Test]
    public function it_labels_the_hourly_forecast_in_a_display_timezone_behind_utc(): void
    {
        Config::set('app.display_timezone', 'America/New_York');
        $this->bindClearForecastStartingAt(CarbonImmutable::parse('2026-10-04 22:00:00', 'UTC'));
        $user = User::factory()->create();

        Livewire::actingAs($user, 'web')
            ->test(MotoDashboard::class)
            ->assertSee('Sun 18h')
            ->assertDontSee('Sun 22h');
    }

    #[Test]
    public function it_labels_the_favorable_slots_in_the_display_timezone(): void
    {
        $this->bindClearForecastStartingAt(CarbonImmutable::parse('2026-10-04 22:00:00', 'UTC'));
        $user = User::factory()->create();
        $displayStart = CarbonImmutable::parse('2026-10-05 00:00:00', 'Europe/Paris')->translatedFormat('D d M, H\h');
        $utcStart = CarbonImmutable::parse('2026-10-04 22:00:00', 'UTC')->translatedFormat('D d M, H\h');

        Livewire::actingAs($user, 'web')
            ->test(MotoDashboard::class)
            ->assertSeeInOrder([$displayStart, '→', '03h'])
            ->assertDontSee($utcStart);
    }

    #[Test]
    public function it_names_the_weekday_of_the_hourly_forecast_in_the_application_locale(): void
    {
        $originalLocale = Carbon::getLocale();
        $this->app->setLocale('fr');
        $this->bindClearForecastStartingAt(CarbonImmutable::parse('2026-10-04 22:00:00', 'UTC'));
        $user = User::factory()->create();
        $frenchLabel = CarbonImmutable::parse('2026-10-05 00:00:00', 'Europe/Paris')->locale('fr')->translatedFormat('D H\h');

        Livewire::actingAs($user, 'web')
            ->test(MotoDashboard::class)
            ->assertSee($frenchLabel)
            ->assertDontSee('Mon 00h');

        Carbon::setLocale($originalLocale);
    }
}

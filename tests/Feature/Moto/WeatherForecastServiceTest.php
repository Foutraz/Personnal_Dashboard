<?php

namespace Tests\Feature\Moto;

use Foutraz\Weather\WeatherManager;
use Functional\Moto\Exceptions\WeatherApiKeyMissingException;
use Functional\Moto\Services\WeatherForecastService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WeatherForecastServiceTest extends TestCase
{
    /**
     * Build a weather forecast service backed by a Guzzle mock handler.
     *
     * @param  array<int, Response>  $responses
     */
    private function serviceWith(array $responses): WeatherForecastService
    {
        $handler = HandlerStack::create(new MockHandler($responses));
        $client = new Client(['handler' => $handler, 'http_errors' => false]);

        return new WeatherForecastService(new WeatherManager('https://api.openweathermap.org', 'test-key', $client));
    }

    #[Test]
    public function it_fetches_and_maps_the_current_weather(): void
    {
        Config::set('weather.api_key', 'test-key');

        $service = $this->serviceWith([
            new Response(200, [], (string) json_encode([
                'dt' => 1_700_000_000,
                'main' => ['temp' => 18.5, 'feels_like' => 17.0, 'humidity' => 60, 'pressure' => 1012],
                'wind' => ['speed' => 3.2, 'gust' => 5.1],
                'clouds' => ['all' => 20],
                'visibility' => 10000,
                'weather' => [['main' => 'Clear', 'description' => 'clear sky']],
                'coord' => ['lat' => 48.85, 'lon' => 2.35],
            ])),
        ]);

        $current = $service->currentWeather(48.85, 2.35);

        $this->assertSame(18.5, $current->temp);
        $this->assertSame('Clear', $current->weatherMain);
    }

    #[Test]
    public function it_fetches_and_maps_the_forecast(): void
    {
        Config::set('weather.api_key', 'test-key');

        $service = $this->serviceWith([
            new Response(200, [], (string) json_encode([
                'city' => ['coord' => ['lat' => 48.85, 'lon' => 2.35]],
                'list' => [
                    [
                        'dt' => 1_700_000_000,
                        'main' => ['temp' => 20.0, 'feels_like' => 20.0],
                        'wind' => ['speed' => 2.0],
                        'pop' => 0.1,
                        'clouds' => ['all' => 5],
                        'visibility' => 10000,
                        'weather' => [['main' => 'Clear', 'description' => 'clear sky']],
                    ],
                ],
            ])),
        ]);

        $forecast = $service->forecast(48.85, 2.35);

        $this->assertCount(1, $forecast->entries);
        $this->assertSame(20.0, $forecast->entries[0]->temp);
    }

    #[Test]
    public function it_reports_being_unconfigured_without_an_api_key(): void
    {
        Config::set('weather.api_key', null);

        $service = $this->serviceWith([]);

        $this->assertFalse($service->isConfigured());
    }

    #[Test]
    public function it_throws_when_fetching_without_an_api_key(): void
    {
        Config::set('weather.api_key', null);

        $service = $this->serviceWith([]);

        $this->expectException(WeatherApiKeyMissingException::class);

        $service->forecast(48.85, 2.35);
    }

    #[Test]
    public function it_searches_cities_by_name(): void
    {
        Config::set('weather.api_key', 'test-key');

        $service = $this->serviceWith([
            new Response(200, [], (string) json_encode([
                ['name' => 'Lyon', 'state' => 'Auvergne-Rhône-Alpes', 'country' => 'FR', 'lat' => 45.7589, 'lon' => 4.8414],
            ])),
        ]);

        $places = $service->searchCity('Lyon');

        $this->assertCount(1, $places);
        $this->assertSame('Lyon, Auvergne-Rhône-Alpes, FR', $places[0]->label());
    }

    #[Test]
    public function it_reverse_geocodes_coordinates(): void
    {
        Config::set('weather.api_key', 'test-key');

        $service = $this->serviceWith([
            new Response(200, [], (string) json_encode([
                ['name' => 'Paris', 'state' => 'Île-de-France', 'country' => 'FR', 'lat' => 48.8566, 'lon' => 2.3522],
            ])),
        ]);

        $place = $service->reverseGeocode(48.8566, 2.3522);

        $this->assertNotNull($place);
        $this->assertSame('Paris, Île-de-France, FR', $place->label());
    }

    #[Test]
    public function it_throws_when_searching_without_an_api_key(): void
    {
        Config::set('weather.api_key', null);

        $service = $this->serviceWith([]);

        $this->expectException(WeatherApiKeyMissingException::class);

        $service->searchCity('Lyon');
    }
}

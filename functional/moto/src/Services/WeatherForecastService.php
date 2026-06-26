<?php

namespace Functional\Moto\Services;

use Foutraz\Weather\Dto\CurrentWeather;
use Foutraz\Weather\Dto\Forecast;
use Foutraz\Weather\WeatherManager;
use Functional\Moto\Exceptions\WeatherApiKeyMissingException;
use Illuminate\Support\Facades\Cache;

class WeatherForecastService
{
    public function __construct(public WeatherManager $weatherManager) {}

    /**
     * Determine whether the OpenWeatherMap API key has been configured.
     */
    public function isConfigured(): bool
    {
        return ! blank(config('weather.api_key'));
    }

    /**
     * Fetch the current weather at the given coordinates using a short cache.
     *
     * @throws WeatherApiKeyMissingException
     */
    public function currentWeather(float $lat, float $lon): CurrentWeather
    {
        $this->guardConfigured();

        return Cache::remember(
            $this->cacheKey('current', $lat, $lon),
            (int) config('moto.forecast.cache_ttl'),
            fn (): CurrentWeather => $this->weatherManager->currentWeather()->at($lat, $lon),
        );
    }

    /**
     * Fetch the forecast at the given coordinates using a short cache.
     *
     * @throws WeatherApiKeyMissingException
     */
    public function forecast(float $lat, float $lon): Forecast
    {
        $this->guardConfigured();

        return Cache::remember(
            $this->cacheKey('forecast', $lat, $lon),
            (int) config('moto.forecast.cache_ttl'),
            fn (): Forecast => $this->weatherManager->forecast()->at($lat, $lon),
        );
    }

    /**
     * Ensure the API key is configured before issuing any request.
     *
     * @throws WeatherApiKeyMissingException
     */
    private function guardConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw WeatherApiKeyMissingException::create();
        }
    }

    /**
     * Build the cache key for the given endpoint and coordinates.
     */
    private function cacheKey(string $endpoint, float $lat, float $lon): string
    {
        return sprintf('moto:weather:%s:%s:%s', $endpoint, round($lat, 3), round($lon, 3));
    }
}

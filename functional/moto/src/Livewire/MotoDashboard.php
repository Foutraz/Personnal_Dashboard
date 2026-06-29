<?php

namespace Functional\Moto\Livewire;

use Foutraz\Weather\Dto\CurrentWeather;
use Foutraz\Weather\Dto\Forecast;
use Foutraz\Weather\Dto\Place;
use Functional\Moto\Models\MotoRide;
use Functional\Moto\Services\FavorableSlotFinder;
use Functional\Moto\Services\MotoFriendlyScore;
use Functional\Moto\Services\RidingStatsCalculator;
use Functional\Moto\Services\WeatherForecastService;
use Functional\Moto\ValueObjects\RideCondition;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

class MotoDashboard extends Component
{
    /**
     * The latitude the forecast is fetched for.
     */
    public float $lat = 0.0;

    /**
     * The longitude the forecast is fetched for.
     */
    public float $lon = 0.0;

    /**
     * The human-readable label of the displayed location.
     */
    public string $locationLabel = '';

    /**
     * The current city search query.
     */
    public string $citySearch = '';

    /**
     * The geocoded city matches for the current query.
     *
     * @var array<int, array{lat: float, lon: float, label: string}>
     */
    public array $cityResults = [];

    /**
     * The title of the ride being logged.
     */
    public string $rideTitle = '';

    /**
     * The start date and time of the ride being logged.
     */
    public string $rideStartedAt = '';

    /**
     * The duration in minutes of the ride being logged.
     */
    public string $rideDuration = '';

    /**
     * The distance in kilometers of the ride being logged.
     */
    public string $rideDistance = '';

    /**
     * The optional note of the ride being logged.
     */
    public string $rideNote = '';

    /**
     * Initialise the location from the configured default.
     */
    public function mount(): void
    {
        $this->lat = (float) config('moto.location.lat');
        $this->lon = (float) config('moto.location.lon');
        $this->locationLabel = (string) config('moto.location.label');
        $this->rideStartedAt = Carbon::now()->format('Y-m-d\TH:i');
    }

    /**
     * Update the displayed location coordinates and label.
     */
    public function setLocation(float $lat, float $lon, string $label): void
    {
        $this->lat = $lat;
        $this->lon = $lon;
        $this->locationLabel = $label;
    }

    /**
     * Populate the city results from the current search query.
     */
    public function searchCity(WeatherForecastService $weatherForecastService): void
    {
        if (mb_strlen(trim($this->citySearch)) < 2 || ! $weatherForecastService->isConfigured()) {
            $this->cityResults = [];

            return;
        }

        $this->cityResults = array_map(
            static fn (Place $place): array => [
                'lat' => $place->lat,
                'lon' => $place->lon,
                'label' => $place->label(),
            ],
            $weatherForecastService->searchCity(trim($this->citySearch)),
        );
    }

    /**
     * Switch the displayed location to the chosen city and clear the search.
     */
    public function chooseCity(float $lat, float $lon, string $label): void
    {
        $this->setLocation($lat, $lon, $label);
        $this->reset('citySearch', 'cityResults');
    }

    /**
     * Apply the browser geolocation, labelling it via reverse geocoding when possible.
     */
    public function applyDeviceLocation(float $lat, float $lon, WeatherForecastService $weatherForecastService): void
    {
        $place = $weatherForecastService->isConfigured()
            ? $weatherForecastService->reverseGeocode($lat, $lon)
            : null;

        $this->setLocation($lat, $lon, $place?->label() ?? 'Ma position');
    }

    /**
     * Create a moto ride owned by the authenticated user from the inline form.
     */
    public function logRide(): void
    {
        $this->validate([
            'rideTitle' => 'required|string|max:255',
            'rideStartedAt' => 'required|date',
            'rideDuration' => 'required|numeric|min:1',
            'rideDistance' => 'required|numeric|min:0',
            'rideNote' => 'nullable|string|max:1000',
        ]);

        MotoRide::query()->create([
            'user_id' => Auth::id(),
            'title' => $this->rideTitle,
            'started_at' => Carbon::parse($this->rideStartedAt),
            'duration' => (int) round((float) $this->rideDuration * 60),
            'distance' => $this->rideDistance,
            'note' => $this->rideNote !== '' ? $this->rideNote : null,
        ]);

        $this->reset('rideTitle', 'rideDuration', 'rideDistance', 'rideNote');
        $this->rideStartedAt = Carbon::now()->format('Y-m-d\TH:i');
    }

    /**
     * Delete the given ride owned by the authenticated user.
     */
    public function deleteRide(string $rideId): void
    {
        MotoRide::query()
            ->where('user_id', Auth::id())
            ->whereKey($rideId)
            ->first()
            ?->delete();
    }

    /**
     * Load all the moto rides of the authenticated user.
     *
     * @return Collection<int, MotoRide>
     */
    public function rides(): Collection
    {
        return MotoRide::query()
            ->where('user_id', Auth::id())
            ->orderByDesc('started_at')
            ->get();
    }

    /**
     * Render the futuristic weather, score gauge, favorable slots and ride log.
     */
    #[Layout('layouts.app')]
    #[Title('Météo & Moto')]
    public function render(
        WeatherForecastService $weatherForecastService,
        MotoFriendlyScore $motoFriendlyScore,
        FavorableSlotFinder $favorableSlotFinder,
        RidingStatsCalculator $ridingStatsCalculator,
    ): View {
        $configured = $weatherForecastService->isConfigured();

        $current = null;
        $hourly = [];
        $condition = null;
        $slots = [];

        if ($configured) {
            $current = $weatherForecastService->currentWeather($this->lat, $this->lon);
            $forecast = $weatherForecastService->forecast($this->lat, $this->lon);
            $hourly = $this->buildHourly($forecast, $motoFriendlyScore);
            $condition = $this->nextWindowCondition($forecast, $motoFriendlyScore, $current);
            $slots = array_map(
                fn ($slot): array => $slot->toArray(),
                $favorableSlotFinder->find($forecast),
            );
        }

        $rides = $this->rides();

        return view('moto::moto', [
            'configured' => $configured,
            'current' => $current,
            'hourly' => $hourly,
            'condition' => $condition?->toArray(),
            'slots' => $slots,
            'rides' => $rides,
            'stats' => [
                'count' => $ridingStatsCalculator->rideCount($rides),
                'distance' => $ridingStatsCalculator->totalDistance($rides),
                'duration' => $ridingStatsCalculator->totalDuration($rides),
                'average_speed' => $ridingStatsCalculator->averageSpeed($rides),
            ],
        ]);
    }

    /**
     * Build the hourly forecast strip with a moto score for each entry.
     *
     * @return array<int, array{label: string, temp: float, pop: float, wind: float, score: int, accent: string}>
     */
    private function buildHourly(Forecast $forecast, MotoFriendlyScore $motoFriendlyScore): array
    {
        return array_map(function ($entry) use ($motoFriendlyScore): array {
            $condition = $motoFriendlyScore->forForecastEntry($entry);

            return [
                'label' => Carbon::instance($entry->dt)->format('D H\h'),
                'temp' => round($entry->temp, 1),
                'pop' => round($entry->pop * 100),
                'wind' => round($entry->windSpeed * 3.6),
                'score' => $condition->score,
                'accent' => $condition->rating->accent(),
            ];
        }, array_slice($forecast->entries, 0, 12));
    }

    /**
     * Build the riding condition of the next forecast window, falling back to the current weather.
     */
    private function nextWindowCondition(Forecast $forecast, MotoFriendlyScore $motoFriendlyScore, CurrentWeather $current): RideCondition
    {
        $entries = $forecast->entries;

        if ($entries === []) {
            return $motoFriendlyScore->forCurrentWeather($current);
        }

        return $motoFriendlyScore->forForecastEntry($entries[0]);
    }
}

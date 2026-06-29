# Moto Weather Location Selection Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let the Météo & Moto dashboard pick a city by name (geocoding) with a default browser geolocation request, and recompute weather for the chosen position.

**Architecture:** Extend the `foutraz/weather` SDK (`../SDK_Weather`) with a geocoding action and a `Place` DTO. The dashboard's `WeatherForecastService` exposes `searchCity()` / `reverseGeocode()`, the `MotoDashboard` Livewire component gains `searchCity()`, `chooseCity()` and `applyDeviceLocation()` actions, and the Blade view adds a search box plus an Alpine geolocation prompt. Changing the component coordinates re-renders and refetches automatically.

**Tech Stack:** PHP 8.4, Laravel 12, Livewire 3, Alpine.js, GuzzleHTTP, PHPUnit 12, OpenWeatherMap Geocoding API (`geo/1.0`).

## Global Constraints

- No try-catch anywhere; rely on existing guards and empty/null returns.
- No inline code comments; docstrings only, one English sentence each.
- Run `vendor/bin/pint --dirty --format agent` in each repo touched before finalizing.
- Two repos: SDK changes land in `../SDK_Weather`; dashboard changes in the project root. The SDK is a `path` repo WITHOUT symlink, so run `composer update foutraz/weather` in the dashboard after SDK changes so the copy in `vendor/` updates.
- Work on the `develop` branch in both repos; commit and push after each commit (user-authorised derogation to the "never push develop" rule). Gitmoji prefix on every commit message, one sentence.
- Geocoding endpoints (base `https://api.openweathermap.org`): `geo/1.0/direct?q={query}&limit={limit}` returns a JSON array of `{name, state?, country, lat, lon}`; `geo/1.0/reverse?lat={lat}&lon={lon}&limit=1` returns the same shape. `appid`/`units` are appended automatically by `MakesHttpRequests::defaultQuery()`.

---

### Task 1: `Place` DTO in the SDK

**Files:**
- Create: `../SDK_Weather/src/Dto/Place.php`
- Test: `../SDK_Weather/tests/Unit/Dto/PlaceTest.php`

**Interfaces:**
- Produces: `Foutraz\Weather\Dto\Place` with public `string $name`, `?string $state`, `string $country`, `float $lat`, `float $lon`; `static fromArray(array $data): self`; `label(): string` joining the non-empty `name`, `state`, `country` with `, `.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Foutraz\Weather\Tests\Unit\Dto;

use Foutraz\Weather\Dto\Place;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PlaceTest extends TestCase
{
    #[Test]
    public function it_maps_a_full_payload(): void
    {
        $place = Place::fromArray([
            'name' => 'Lyon',
            'state' => 'Auvergne-Rhône-Alpes',
            'country' => 'FR',
            'lat' => 45.7589,
            'lon' => 4.8414,
        ]);

        $this->assertSame('Lyon', $place->name);
        $this->assertSame('Auvergne-Rhône-Alpes', $place->state);
        $this->assertSame('FR', $place->country);
        $this->assertSame(45.7589, $place->lat);
        $this->assertSame(4.8414, $place->lon);
        $this->assertSame('Lyon, Auvergne-Rhône-Alpes, FR', $place->label());
    }

    #[Test]
    public function it_omits_a_missing_state_from_the_label(): void
    {
        $place = Place::fromArray([
            'name' => 'Singapore',
            'country' => 'SG',
            'lat' => 1.2897,
            'lon' => 103.8501,
        ]);

        $this->assertNull($place->state);
        $this->assertSame('Singapore, SG', $place->label());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd ../SDK_Weather && vendor/bin/phpunit --filter=PlaceTest`
Expected: FAIL with "Class \"Foutraz\Weather\Dto\Place\" not found".

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace Foutraz\Weather\Dto;

class Place
{
    public function __construct(
        public string $name,
        public ?string $state,
        public string $country,
        public float $lat,
        public float $lon,
    ) {}

    /**
     * Builds a place from an OpenWeatherMap geocoding entry.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['name'] ?? ''),
            isset($data['state']) ? (string) $data['state'] : null,
            (string) ($data['country'] ?? ''),
            (float) ($data['lat'] ?? 0.0),
            (float) ($data['lon'] ?? 0.0),
        );
    }

    /**
     * Renders a human-readable label from the non-empty location parts.
     */
    public function label(): string
    {
        return implode(', ', array_filter([$this->name, $this->state, $this->country]));
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd ../SDK_Weather && vendor/bin/phpunit --filter=PlaceTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
cd ../SDK_Weather
vendor/bin/pint --dirty --format agent
git add src/Dto/Place.php tests/Unit/Dto/PlaceTest.php
git commit -m "✨ add Place DTO for geocoding results"
git push
```

---

### Task 2: `ManagesGeocoding` action in the SDK

**Files:**
- Create: `../SDK_Weather/src/Actions/ManagesGeocoding.php`
- Modify: `../SDK_Weather/src/WeatherManager.php`
- Test: `../SDK_Weather/tests/Feature/Actions/ManagesGeocodingTest.php`

**Interfaces:**
- Consumes: `Foutraz\Weather\Dto\Place` (Task 1); `MakesHttpRequests::get()`.
- Produces: `WeatherManager::geocoding(): ManagesGeocoding`; `ManagesGeocoding::search(string $query, int $limit = 5): array` (array of `Place`); `ManagesGeocoding::reverse(float $lat, float $lon): ?Place`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Foutraz\Weather\Tests\Feature\Actions;

use Foutraz\Weather\Dto\Place;
use Foutraz\Weather\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ManagesGeocodingTest extends TestCase
{
    #[Test]
    public function it_searches_places_by_name(): void
    {
        $manager = $this->managerWithResponses([
            $this->jsonResponse(200, [
                ['name' => 'Lyon', 'state' => 'Auvergne-Rhône-Alpes', 'country' => 'FR', 'lat' => 45.7589, 'lon' => 4.8414],
                ['name' => 'Lyon', 'country' => 'US', 'lat' => 42.4759, 'lon' => -71.6884],
            ]),
        ]);

        $places = $manager->geocoding()->search('Lyon');

        $this->assertCount(2, $places);
        $this->assertInstanceOf(Place::class, $places[0]);
        $this->assertSame('Lyon, Auvergne-Rhône-Alpes, FR', $places[0]->label());
        $this->assertStringContainsString('geo/1.0/direct', $this->lastRequestUri());
        $this->assertStringContainsString('q=Lyon', $this->lastRequestUri());
    }

    #[Test]
    public function it_returns_an_empty_array_when_no_place_matches(): void
    {
        $manager = $this->managerWithResponses([
            $this->jsonResponse(200, []),
        ]);

        $this->assertSame([], $manager->geocoding()->search('Zzzzzz'));
    }

    #[Test]
    public function it_reverse_geocodes_coordinates(): void
    {
        $manager = $this->managerWithResponses([
            $this->jsonResponse(200, [
                ['name' => 'Paris', 'state' => 'Île-de-France', 'country' => 'FR', 'lat' => 48.8566, 'lon' => 2.3522],
            ]),
        ]);

        $place = $manager->geocoding()->reverse(48.8566, 2.3522);

        $this->assertInstanceOf(Place::class, $place);
        $this->assertSame('Paris, Île-de-France, FR', $place->label());
        $this->assertStringContainsString('geo/1.0/reverse', $this->lastRequestUri());
    }

    #[Test]
    public function it_returns_null_when_reverse_geocoding_finds_nothing(): void
    {
        $manager = $this->managerWithResponses([
            $this->jsonResponse(200, []),
        ]);

        $this->assertNull($manager->geocoding()->reverse(0.0, 0.0));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd ../SDK_Weather && vendor/bin/phpunit --filter=ManagesGeocodingTest`
Expected: FAIL with "Call to undefined method ...::geocoding()".

- [ ] **Step 3: Write minimal implementation**

Create `../SDK_Weather/src/Actions/ManagesGeocoding.php`:

```php
<?php

namespace Foutraz\Weather\Actions;

use Foutraz\Weather\Dto\Place;
use Foutraz\Weather\Exceptions\ActionFailed;
use Foutraz\Weather\Exceptions\InvalidData;
use Foutraz\Weather\Exceptions\ResourceNotFound;
use Foutraz\Weather\Exceptions\TooManyRequestsException;
use Foutraz\Weather\Exceptions\Unauthorized;
use Foutraz\Weather\WeatherManager;
use GuzzleHttp\Exception\GuzzleException;

class ManagesGeocoding extends WeatherManager
{
    /**
     * Searches geocoded places matching the given name.
     *
     * @return array<int, Place>
     *
     * @throws ActionFailed
     * @throws GuzzleException
     * @throws InvalidData
     * @throws ResourceNotFound
     * @throws TooManyRequestsException
     * @throws Unauthorized
     */
    public function search(string $query, int $limit = 5): array
    {
        $results = $this->get('geo/1.0/direct', ['q' => $query, 'limit' => $limit]);

        return array_map(static fn (array $place): Place => Place::fromArray($place), $results);
    }

    /**
     * Resolves the closest place for the given coordinates.
     *
     * @throws ActionFailed
     * @throws GuzzleException
     * @throws InvalidData
     * @throws ResourceNotFound
     * @throws TooManyRequestsException
     * @throws Unauthorized
     */
    public function reverse(float $lat, float $lon): ?Place
    {
        $results = $this->get('geo/1.0/reverse', ['lat' => $lat, 'lon' => $lon, 'limit' => 1]);

        return isset($results[0]) ? Place::fromArray($results[0]) : null;
    }
}
```

Modify `../SDK_Weather/src/WeatherManager.php` — add the import and the accessor next to `forecast()`:

```php
use Foutraz\Weather\Actions\ManagesGeocoding;
```

```php
    public function geocoding(): ManagesGeocoding
    {
        return new ManagesGeocoding($this->endpoint, $this->apiKey, $this->client);
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd ../SDK_Weather && vendor/bin/phpunit --filter=ManagesGeocodingTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Run the full SDK suite**

Run: `cd ../SDK_Weather && vendor/bin/phpunit`
Expected: PASS (all green).

- [ ] **Step 6: Commit**

```bash
cd ../SDK_Weather
vendor/bin/pint --dirty --format agent
git add src/Actions/ManagesGeocoding.php src/WeatherManager.php tests/Feature/Actions/ManagesGeocodingTest.php
git commit -m "✨ add geocoding action to the weather manager"
git push
```

---

### Task 3: Pull the updated SDK into the dashboard

**Files:**
- Modify: `composer.lock` (regenerated)

- [ ] **Step 1: Refresh the path-repo copy**

Run (on the host): `composer update foutraz/weather`
Expected: `foutraz/weather` recopied into `vendor/`; `vendor/foutraz/weather/src/Actions/ManagesGeocoding.php` now exists.

- [ ] **Step 2: Verify the new class is autoloadable**

Run: `php artisan tinker --execute 'echo class_exists(\Foutraz\Weather\Dto\Place::class) ? "ok" : "missing";'`
Expected: prints `ok`.

- [ ] **Step 3: Commit**

```bash
git add composer.lock
git commit -m "⬆️ pull geocoding support from the weather SDK"
git push
```

---

### Task 4: Geocoding methods on `WeatherForecastService`

**Files:**
- Modify: `functional/moto/src/Services/WeatherForecastService.php`
- Test: `tests/Feature/Moto/WeatherForecastServiceTest.php`

**Interfaces:**
- Consumes: `WeatherManager::geocoding()` (Task 2); `Foutraz\Weather\Dto\Place`.
- Produces: `WeatherForecastService::searchCity(string $query): array` (array of `Place`); `WeatherForecastService::reverseGeocode(float $lat, float $lon): ?Place`.

- [ ] **Step 1: Write the failing test**

Append these two methods to `tests/Feature/Moto/WeatherForecastServiceTest.php` (inside the class):

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=it_searches_cities_by_name`
Expected: FAIL with "Call to undefined method ...WeatherForecastService::searchCity()".

- [ ] **Step 3: Write minimal implementation**

Add the import to `functional/moto/src/Services/WeatherForecastService.php`:

```php
use Foutraz\Weather\Dto\Place;
```

Add these two methods after `forecast()`:

```php
    /**
     * Search geocoded cities matching the given name.
     *
     * @return array<int, Place>
     *
     * @throws WeatherApiKeyMissingException
     */
    public function searchCity(string $query): array
    {
        $this->guardConfigured();

        return $this->weatherManager->geocoding()->search($query);
    }

    /**
     * Resolve the closest place for the given coordinates using a short cache.
     *
     * @throws WeatherApiKeyMissingException
     */
    public function reverseGeocode(float $lat, float $lon): ?Place
    {
        $this->guardConfigured();

        return Cache::remember(
            $this->cacheKey('reverse', $lat, $lon),
            (int) config('moto.forecast.cache_ttl'),
            fn (): ?Place => $this->weatherManager->geocoding()->reverse($lat, $lon),
        );
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=WeatherForecastServiceTest`
Expected: PASS (all tests in the file).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add functional/moto/src/Services/WeatherForecastService.php tests/Feature/Moto/WeatherForecastServiceTest.php
git commit -m "✨ expose city search and reverse geocoding in the forecast service"
git push
```

---

### Task 5: Location actions on the `MotoDashboard` component

**Files:**
- Modify: `functional/moto/src/Livewire/MotoDashboard.php`
- Test: `tests/Feature/Moto/MotoDashboardComponentTest.php`

**Interfaces:**
- Consumes: `WeatherForecastService::searchCity()` / `reverseGeocode()` (Task 4); existing `MotoDashboard::setLocation()`; `Foutraz\Weather\Dto\Place`.
- Produces: public `string $citySearch`, `array $cityResults`; actions `searchCity(WeatherForecastService $weatherForecastService): void`, `chooseCity(float $lat, float $lon, string $label): void`, `applyDeviceLocation(float $lat, float $lon, WeatherForecastService $weatherForecastService): void`. `$cityResults` entries are `array{lat: float, lon: float, label: string}`.

- [ ] **Step 1: Write the failing tests**

Add a geocoding-aware binding helper and three tests to `tests/Feature/Moto/MotoDashboardComponentTest.php`.

Add this helper method inside the class:

```php
    /**
     * Bind a weather manager replaying the queued responses for geocoding flows.
     *
     * @param  array<int, Response>  $responses
     */
    private function bindWeatherResponses(array $responses): void
    {
        Config::set('weather.api_key', 'test-key');

        $handler = HandlerStack::create(new MockHandler($responses));
        $client = new Client(['handler' => $handler, 'http_errors' => false]);

        $this->app->instance(WeatherManager::class, new WeatherManager('https://api.openweathermap.org', 'test-key', $client));
    }
```

Add these tests:

```php
    #[Test]
    public function it_changes_the_location_when_a_city_is_chosen(): void
    {
        Config::set('weather.api_key', null);
        $user = User::factory()->create();

        Livewire::actingAs($user, 'web')
            ->test(MotoDashboard::class)
            ->set('citySearch', 'Lyon')
            ->call('chooseCity', 45.7589, 4.8414, 'Lyon, Auvergne-Rhône-Alpes, FR')
            ->assertSet('lat', 45.7589)
            ->assertSet('lon', 4.8414)
            ->assertSet('locationLabel', 'Lyon, Auvergne-Rhône-Alpes, FR')
            ->assertSet('citySearch', '')
            ->assertSet('cityResults', []);
    }

    #[Test]
    public function it_labels_the_device_location_with_a_fallback_when_unconfigured(): void
    {
        Config::set('weather.api_key', null);
        $user = User::factory()->create();

        Livewire::actingAs($user, 'web')
            ->test(MotoDashboard::class)
            ->call('applyDeviceLocation', 45.7589, 4.8414)
            ->assertSet('lat', 45.7589)
            ->assertSet('locationLabel', 'Ma position');
    }

    #[Test]
    public function it_populates_city_results_from_a_search(): void
    {
        $this->bindWeatherResponses([
            new Response(200, [], (string) json_encode([
                'dt' => 1_700_000_000,
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
                    'dt' => 1_700_000_000,
                    'main' => ['temp' => 21.0, 'feels_like' => 21.0],
                    'wind' => ['speed' => 2.0],
                    'pop' => 0.0,
                    'clouds' => ['all' => 5],
                    'visibility' => 10000,
                    'weather' => [['main' => 'Clear', 'description' => 'clear sky']],
                ]],
            ])),
            new Response(200, [], (string) json_encode([
                ['name' => 'Lyon', 'state' => 'Auvergne-Rhône-Alpes', 'country' => 'FR', 'lat' => 45.7589, 'lon' => 4.8414],
            ])),
        ]);

        $user = User::factory()->create();

        Livewire::actingAs($user, 'web')
            ->test(MotoDashboard::class)
            ->set('citySearch', 'Lyon')
            ->call('searchCity')
            ->assertSet('cityResults', [
                ['lat' => 45.7589, 'lon' => 4.8414, 'label' => 'Lyon, Auvergne-Rhône-Alpes, FR'],
            ]);
    }

    #[Test]
    public function it_clears_city_results_for_a_too_short_query(): void
    {
        Config::set('weather.api_key', null);
        $user = User::factory()->create();

        Livewire::actingAs($user, 'web')
            ->test(MotoDashboard::class)
            ->set('citySearch', 'L')
            ->call('searchCity')
            ->assertSet('cityResults', []);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=it_changes_the_location_when_a_city_is_chosen`
Expected: FAIL with "Method ...chooseCity does not exist" / unable to call.

- [ ] **Step 3: Write minimal implementation**

Add the import to `functional/moto/src/Livewire/MotoDashboard.php`:

```php
use Foutraz\Weather\Dto\Place;
```

Add the two public properties after `$lon`/`$locationLabel` (keep the existing docstring style):

```php
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
```

Add these actions after the existing `setLocation()` method:

```php
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
```

Add the import for the service if not already present (it is used in `render()` already, so `use Functional\Moto\Services\WeatherForecastService;` exists).

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=MotoDashboardComponentTest`
Expected: PASS (all tests in the file).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add functional/moto/src/Livewire/MotoDashboard.php tests/Feature/Moto/MotoDashboardComponentTest.php
git commit -m "✨ add city search and device location actions to the moto dashboard"
git push
```

---

### Task 6: Location search UI and geolocation prompt in the Blade view

**Files:**
- Modify: `functional/moto/resources/views/moto.blade.php`

**Interfaces:**
- Consumes: `citySearch`, `cityResults`, `locationLabel`, `searchCity`, `chooseCity`, `applyDeviceLocation` from Task 5; the `$configured` flag already passed to the view.

- [ ] **Step 1: Add the geolocation prompt and the location toolbar**

Immediately after the closing `</section>` of the intro block (the `<section class="reveal">` ending right before `@unless ($configured)`), insert:

```blade
    <div x-data x-init="navigator.geolocation && navigator.geolocation.getCurrentPosition(
        (position) => $wire.applyDeviceLocation(position.coords.latitude, position.coords.longitude),
        () => {},
        { enableHighAccuracy: false, timeout: 8000 },
    )"></div>

    @if ($configured)
        <section class="reveal mt-6" style="animation-delay: 0.04s;">
            <x-ui.glass-card>
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl border border-cyan/40 bg-cyan-soft text-cyan">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s-6-5.686-6-10a6 6 0 1 1 12 0c0 4.314-6 10-6 10Z"/><circle cx="12" cy="11" r="2.2"/></svg>
                        </span>
                        <div>
                            <p class="text-[0.65rem] uppercase tracking-[0.25em] text-faint">Localisation</p>
                            <h3 class="font-display text-base font-semibold">{{ $locationLabel }}</h3>
                        </div>
                    </div>

                    <div class="relative w-full sm:w-80">
                        <input
                            type="search"
                            wire:model.live="citySearch"
                            wire:keydown.enter.prevent="searchCity"
                            placeholder="Rechercher une ville…"
                            class="w-full rounded-xl border border-hairline bg-surface/50 px-4 py-2.5 text-sm text-ink placeholder:text-faint focus:border-cyan/50 focus:outline-none"
                        />

                        @if (! empty($cityResults))
                            <ul class="absolute z-20 mt-2 w-full overflow-hidden rounded-xl border border-hairline bg-surface shadow-xl">
                                @foreach ($cityResults as $result)
                                    <li>
                                        <button
                                            type="button"
                                            wire:click="chooseCity({{ $result['lat'] }}, {{ $result['lon'] }}, @js($result['label']))"
                                            class="block w-full px-4 py-2.5 text-left text-sm text-muted transition hover:bg-cyan-soft hover:text-cyan"
                                        >
                                            {{ $result['label'] }}
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </x-ui.glass-card>
        </section>
    @endif
```

- [ ] **Step 2: Build the frontend assets**

Run: `npm run build`
Expected: Vite build succeeds (Alpine `$wire`/`x-data` and Livewire directives are already bundled; this confirms no asset error).

- [ ] **Step 3: Verify the component still renders with mocked weather**

Run: `php artisan test --compact --filter=it_renders_the_moto_friendly_score_with_mocked_weather`
Expected: PASS (the view compiles and renders with the new markup).

- [ ] **Step 4: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add functional/moto/resources/views/moto.blade.php
git commit -m "💄 add city search and geolocation prompt to the moto dashboard"
git push
```

---

### Task 7: Full verification

- [ ] **Step 1: Run the moto feature suite**

Run: `php artisan test --compact tests/Feature/Moto tests/Unit/Moto`
Expected: PASS (all green).

- [ ] **Step 2: Run the SDK suite**

Run: `cd ../SDK_Weather && vendor/bin/phpunit`
Expected: PASS (all green).

- [ ] **Step 3: Offer to run the full dashboard suite**

Ask the user whether to run `php artisan test --compact` for the whole project before wrapping up.

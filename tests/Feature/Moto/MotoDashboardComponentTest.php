<?php

namespace Tests\Feature\Moto;

use Foutraz\Weather\WeatherManager;
use Functional\Moto\Livewire\MotoDashboard;
use Functional\Moto\Models\MotoRide;
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

class MotoDashboardComponentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bind a weather manager returning canned current weather and forecast payloads.
     */
    private function bindMockedWeather(): void
    {
        Config::set('weather.api_key', 'test-key');

        $handler = HandlerStack::create(new MockHandler([
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
                'list' => [
                    [
                        'dt' => 1_700_000_000,
                        'main' => ['temp' => 21.0, 'feels_like' => 21.0],
                        'wind' => ['speed' => 2.0],
                        'pop' => 0.0,
                        'clouds' => ['all' => 5],
                        'visibility' => 10000,
                        'weather' => [['main' => 'Clear', 'description' => 'clear sky']],
                    ],
                ],
            ])),
        ]));

        $client = new Client(['handler' => $handler, 'http_errors' => false]);

        $this->app->instance(WeatherManager::class, new WeatherManager('https://api.openweathermap.org', 'test-key', $client));
    }

    #[Test]
    public function it_renders_the_moto_friendly_score_with_mocked_weather(): void
    {
        $this->bindMockedWeather();
        $user = User::factory()->create();

        Livewire::actingAs($user, 'web')
            ->test(MotoDashboard::class)
            ->assertOk()
            ->assertSee('Moto Friendly')
            ->assertSee('Excellent');
    }

    #[Test]
    public function it_degrades_gracefully_without_an_api_key(): void
    {
        Config::set('weather.api_key', null);
        $user = User::factory()->create();

        Livewire::actingAs($user, 'web')
            ->test(MotoDashboard::class)
            ->assertOk()
            ->assertSee('configurer la clé météo');
    }

    #[Test]
    public function it_logs_a_ride_owned_by_the_authenticated_user(): void
    {
        Config::set('weather.api_key', null);
        $user = User::factory()->create();

        Livewire::actingAs($user, 'web')
            ->test(MotoDashboard::class)
            ->set('rideTitle', 'Sortie test')
            ->set('rideStartedAt', now()->subHour()->format('Y-m-d\TH:i'))
            ->set('rideDuration', '90')
            ->set('rideDistance', '120')
            ->call('logRide')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('moto_rides', [
            'title' => 'Sortie test',
            'user_id' => $user->id,
            'duration' => 5400,
        ]);
    }

    #[Test]
    public function it_validates_the_ride_form(): void
    {
        Config::set('weather.api_key', null);
        $user = User::factory()->create();

        Livewire::actingAs($user, 'web')
            ->test(MotoDashboard::class)
            ->set('rideTitle', '')
            ->set('rideDistance', '')
            ->call('logRide')
            ->assertHasErrors(['rideTitle' => 'required', 'rideDistance' => 'required']);
    }

    #[Test]
    public function it_deletes_a_ride(): void
    {
        Config::set('weather.api_key', null);
        $user = User::factory()->create();
        $ride = MotoRide::factory()->create(['user_id' => $user->id]);

        Livewire::actingAs($user, 'web')
            ->test(MotoDashboard::class)
            ->call('deleteRide', $ride->id);

        $this->assertSoftDeleted('moto_rides', ['id' => $ride->id]);
    }

    #[Test]
    public function it_does_not_delete_another_users_ride(): void
    {
        Config::set('weather.api_key', null);
        $user = User::factory()->create();
        $other = User::factory()->create();
        $ride = MotoRide::factory()->create(['user_id' => $other->id]);

        Livewire::actingAs($user, 'web')
            ->test(MotoDashboard::class)
            ->call('deleteRide', $ride->id);

        $this->assertDatabaseHas('moto_rides', ['id' => $ride->id, 'deleted_at' => null]);
    }

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
}

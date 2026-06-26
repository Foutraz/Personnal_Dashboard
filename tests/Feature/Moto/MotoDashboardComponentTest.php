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
}

<?php

namespace Tests\Feature\Sport;

use Functional\Sport\Livewire\ConnectStrava;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Models\IntegrationConnection;
use Tests\TestCase;

class ConnectStravaComponentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_shows_the_connect_state_when_no_connection_exists(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user, 'web')
            ->test(ConnectStrava::class)
            ->assertOk()
            ->assertSee('Non connecté')
            ->assertSee('Connecter Strava')
            ->assertDontSee('Synchroniser');
    }

    #[Test]
    public function it_shows_the_connected_state_when_a_connection_exists(): void
    {
        $user = User::factory()->create();

        IntegrationConnection::factory()->for($user)->create([
            'provider' => IntegrationProvider::Strava,
        ]);

        Livewire::actingAs($user, 'web')
            ->test(ConnectStrava::class)
            ->assertOk()
            ->assertSee('Connecté')
            ->assertSee('Synchroniser');
    }
}

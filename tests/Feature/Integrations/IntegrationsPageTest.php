<?php

namespace Tests\Feature\Integrations;

use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Technical\Integrations\Enums\IntegrationProvider;
use Technical\Integrations\Livewire\IntegrationsManager;
use Technical\Integrations\Models\IntegrationConnection;
use Tests\TestCase;

class IntegrationsPageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_redirects_guests_to_the_login_route(): void
    {
        $this->get('/integrations')->assertRedirect(route('login'));
    }

    #[Test]
    public function it_renders_the_provider_catalogue_for_an_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web')->get('/integrations')
            ->assertOk()
            ->assertSee('Strava')
            ->assertSee('Google Calendar')
            ->assertSee('Liberty Rider')
            ->assertSee('Bientôt')
            ->assertSee('Compte Google');
    }

    #[Test]
    public function it_marks_a_connected_provider_as_connected(): void
    {
        $user = User::factory()->create();
        IntegrationConnection::factory()->for($user)->create(['provider' => IntegrationProvider::Strava]);

        Livewire::actingAs($user, 'web')
            ->test(IntegrationsManager::class)
            ->assertSee('Connecté')
            ->assertSee('Synchroniser');
    }

    #[Test]
    public function it_disconnects_the_authenticated_users_provider(): void
    {
        $user = User::factory()->create();
        $connection = IntegrationConnection::factory()->for($user)->create(['provider' => IntegrationProvider::Strava]);

        Livewire::actingAs($user, 'web')
            ->test(IntegrationsManager::class)
            ->call('disconnect', IntegrationProvider::Strava->value)
            ->assertOk();

        $this->assertSoftDeleted('integration_connections', ['id' => $connection->id]);
    }

    #[Test]
    public function it_does_not_disconnect_another_users_provider(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $otherConnection = IntegrationConnection::factory()->for($other)->create(['provider' => IntegrationProvider::Strava]);

        Livewire::actingAs($user, 'web')
            ->test(IntegrationsManager::class)
            ->call('disconnect', IntegrationProvider::Strava->value);

        $this->assertDatabaseHas('integration_connections', [
            'id' => $otherConnection->id,
            'deleted_at' => null,
        ]);
    }
}

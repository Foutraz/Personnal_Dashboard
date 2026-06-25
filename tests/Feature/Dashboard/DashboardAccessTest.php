<?php

namespace Tests\Feature\Dashboard;

use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Technical\WebAuthentication\Livewire\Dashboard;
use Tests\TestCase;

class DashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_redirects_guests_to_the_login_route(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function it_renders_the_module_grid_for_an_authenticated_web_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Sport');
        $response->assertSee('Finance');
        $response->assertSee('Cartes');
    }

    #[Test]
    public function it_exposes_every_readme_module_through_the_livewire_component(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user, 'web')
            ->test(Dashboard::class)
            ->assertOk()
            ->assertSee('Météo & Moto')
            ->assertSee('Échéances')
            ->assertSee('Objectifs');
    }
}

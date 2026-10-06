<?php

namespace Tests\Feature\Integrations;

use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Technical\Integrations\Livewire\IntegrationsManager;
use Tests\TestCase;

class GoogleAccountLinkHintTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_hides_the_google_link_hint_from_a_user_whose_email_is_unverified(): void
    {
        $user = User::factory()->unverified()->create();

        Livewire::actingAs($user, 'web')
            ->test(IntegrationsManager::class)
            ->assertSee('Non lié')
            ->assertDontSee('Continuer avec Google');
    }

    #[Test]
    public function it_shows_the_google_link_hint_to_a_user_whose_email_is_verified(): void
    {
        $user = User::factory()->verified()->create(['google_id' => null]);

        Livewire::actingAs($user, 'web')
            ->test(IntegrationsManager::class)
            ->assertSee('Non lié')
            ->assertSee('Continuer avec Google');
    }

    #[Test]
    public function it_shows_the_linked_google_account_without_the_hint(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'linked@example.com',
            'google_id' => 'google-linked',
        ]);

        Livewire::actingAs($user, 'web')
            ->test(IntegrationsManager::class)
            ->assertSee('Connexion liée à linked@example.com')
            ->assertDontSee('Continuer avec Google');
    }
}

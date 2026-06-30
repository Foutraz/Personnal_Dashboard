<?php

namespace Tests\Feature\Dashboard;

use Functional\Todo\Models\Task;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Technical\WebAuthentication\Livewire\Dashboard;
use Tests\TestCase;

class DashboardAgendaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_renders_an_upcoming_agenda_item_on_the_dashboard(): void
    {
        $user = User::factory()->create();
        Task::factory()->create(['user_id' => $user->id, 'title' => 'Payer le loyer', 'due_at' => Carbon::now()->addDays(2)]);

        Livewire::actingAs($user, 'web')
            ->test(Dashboard::class)
            ->assertViewHas('agenda', fn ($agenda): bool => $agenda->isNotEmpty())
            ->assertSee('Payer le loyer');
    }
}

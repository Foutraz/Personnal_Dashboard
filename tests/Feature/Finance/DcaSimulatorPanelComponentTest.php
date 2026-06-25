<?php

namespace Tests\Feature\Finance;

use Functional\Finance\Livewire\DcaSimulatorPanel;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DcaSimulatorPanelComponentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_recomputes_the_projected_value_when_the_sliders_change(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(DcaSimulatorPanel::class)
            ->set('periodicAmount', 100)
            ->set('frequency', 'monthly')
            ->set('years', 10)
            ->set('annualReturnRate', 0)
            ->assertViewHas('finalInvested', 12000.0)
            ->assertViewHas('finalValue', 12000.0)
            ->set('annualReturnRate', 7)
            ->assertViewHas('finalInvested', 12000.0);
    }
}

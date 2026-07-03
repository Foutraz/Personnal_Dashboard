<?php

namespace Tests\Feature\Dashboard;

use Functional\Sport\Models\SportActivity;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Technical\Osdd\Dto\DashboardSummary;
use Technical\WebAuthentication\Livewire\Dashboard;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_passes_one_ordered_summary_per_module_to_the_view(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user, 'web')
            ->test(Dashboard::class)
            ->assertViewHas('summaries', function (Collection $summaries): bool {
                $orders = $summaries->map(fn (DashboardSummary $summary): int => $summary->order)->all();
                $sorted = $orders;
                sort($sorted);

                return $summaries->count() === 11 && $orders === $sorted;
            });
    }

    #[Test]
    public function it_scopes_the_sport_metric_to_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        SportActivity::factory()->create(['user_id' => User::factory()->create()->id, 'distance' => 50000.0]);

        Livewire::actingAs($user, 'web')
            ->test(Dashboard::class)
            ->assertViewHas('summaries', function (Collection $summaries): bool {
                $sport = $summaries->firstWhere(fn (DashboardSummary $summary): bool => $summary->key === 'sport');

                return $sport !== null && $sport->metricValue === '0';
            });
    }
}

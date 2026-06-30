<?php

namespace Tests\Feature\Dashboard;

use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Technical\Osdd\Dto\DashboardSummary;
use Technical\Osdd\Dto\NavigationItem;
use Technical\WebAuthentication\Services\DashboardSummaryCollector;
use Technical\WebAuthentication\Services\NavigationItemCollector;
use Tests\Feature\Dashboard\Fixtures\EarlierFakeDashboardContribution;
use Tests\Feature\Dashboard\Fixtures\FakeDashboardContribution;
use Tests\TestCase;

class DashboardCollectorsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_discovers_a_tagged_summary_without_touching_the_dashboard(): void
    {
        $this->app->tag(FakeDashboardContribution::class, ['dashboard.summaries']);
        $user = User::factory()->create();

        $summaries = $this->app->make(DashboardSummaryCollector::class)->for($user);

        $this->assertTrue($summaries->contains(fn (DashboardSummary $summary): bool => $summary->key === 'fake'));
    }

    #[Test]
    public function it_discovers_a_tagged_navigation_item(): void
    {
        $this->app->tag(FakeDashboardContribution::class, ['dashboard.navigation']);

        $items = $this->app->make(NavigationItemCollector::class)->all();

        $this->assertTrue($items->contains(fn (NavigationItem $item): bool => $item->label === 'Fake'));
    }

    #[Test]
    public function it_orders_navigation_items_by_their_order(): void
    {
        $this->app->tag(FakeDashboardContribution::class, ['dashboard.navigation']);
        $this->app->tag(EarlierFakeDashboardContribution::class, ['dashboard.navigation']);

        $items = $this->app->make(NavigationItemCollector::class)->all();
        $orders = $items->map(fn (NavigationItem $item): int => $item->order)->values()->all();
        $sorted = $orders;
        sort($sorted);

        $this->assertSame($sorted, $orders);
        $fakeOrders = $items
            ->filter(fn (NavigationItem $item): bool => in_array($item->label, ['Fake', 'Earlier Fake'], true))
            ->map(fn (NavigationItem $item): int => $item->order)
            ->values()
            ->all();
        $this->assertSame([1, 5], $fakeOrders);
    }
}

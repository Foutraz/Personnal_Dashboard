<?php

namespace Tests\Feature\Dashboard;

use Carbon\CarbonPeriod;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Technical\Osdd\Dto\AgendaItem;
use Technical\WebAuthentication\Services\AgendaCollector;
use Tests\Feature\Dashboard\Fixtures\FakeAgendaProvider;
use Tests\TestCase;

class AgendaCollectorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_aggregates_tagged_providers_sorted_chronologically(): void
    {
        $this->app->bind('fake.later', fn (): FakeAgendaProvider => new FakeAgendaProvider('later', Carbon::parse('2026-07-10 09:00')));
        $this->app->bind('fake.sooner', fn (): FakeAgendaProvider => new FakeAgendaProvider('sooner', Carbon::parse('2026-07-02 09:00')));
        $this->app->tag(['fake.later', 'fake.sooner'], 'dashboard.agenda');
        $user = User::factory()->create();

        $items = $this->app->make(AgendaCollector::class)->for($user, CarbonPeriod::create('2026-07-01', '2026-07-31'));

        $ids = $items->map(fn (AgendaItem $item): string => $item->id)->all();
        $this->assertSame(['sooner', 'later'], $ids);
    }
}

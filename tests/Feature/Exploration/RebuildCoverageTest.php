<?php

namespace Tests\Feature\Exploration;

use Functional\Exploration\Actions\RebuildUserCoverage;
use Functional\Exploration\Events\CoverageRebuilt;
use Functional\Exploration\Jobs\RebuildCoverageJob;
use Functional\Exploration\Models\ExploredCell;
use Functional\Sport\Models\SportActivity;
use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Technical\Integrations\Models\IntegrationConnection;
use Tests\TestCase;

class RebuildCoverageTest extends TestCase
{
    use RefreshDatabase;

    private const POLYLINE = '_p~iF~ps|U_ulLnnqC_mqNvxq`@';

    private const FORGED_CELL_KEY = '999999:999999';

    private function createRoutedActivity(User $user): SportActivity
    {
        $connection = IntegrationConnection::factory()->for($user)->create();

        return SportActivity::factory()->create([
            'user_id' => $user->id,
            'integration_connection_id' => $connection->id,
            'map_polyline' => self::POLYLINE,
        ]);
    }

    private function cellKeysOf(User $user): array
    {
        return ExploredCell::query()->whereBelongsTo($user)->orderBy('cell_key')->pluck('cell_key')->all();
    }

    private function identityOfCellsOf(User $user): array
    {
        return ExploredCell::query()
            ->whereBelongsTo($user)
            ->orderBy('cell_key')
            ->get()
            ->map(fn (ExploredCell $cell): array => [$cell->cell_key, $cell->id, $cell->created_at->toDateTimeString()])
            ->all();
    }

    #[Test]
    public function it_builds_explored_cells_from_activity_polylines(): void
    {
        $user = User::factory()->create();
        $connection = IntegrationConnection::factory()->for($user)->create();

        SportActivity::factory()->create([
            'user_id' => $user->id,
            'integration_connection_id' => $connection->id,
            'map_polyline' => '_p~iF~ps|U_ulLnnqC_mqNvxq`@',
        ]);

        $count = app(RebuildUserCoverage::class)->handle($user->id);

        $this->assertSame(3, $count);
        $this->assertSame(3, ExploredCell::query()->where('user_id', $user->id)->count());
    }

    #[Test]
    public function it_is_idempotent_when_rebuilt_twice(): void
    {
        $user = User::factory()->create();
        $connection = IntegrationConnection::factory()->for($user)->create();

        SportActivity::factory()->create([
            'user_id' => $user->id,
            'integration_connection_id' => $connection->id,
            'map_polyline' => '_p~iF~ps|U_ulLnnqC_mqNvxq`@',
        ]);

        app(RebuildUserCoverage::class)->handle($user->id);
        app(RebuildUserCoverage::class)->handle($user->id);

        $this->assertSame(3, ExploredCell::query()->where('user_id', $user->id)->count());
    }

    #[Test]
    public function it_dispatches_the_rebuilt_event_after_the_job_run(): void
    {
        $user = User::factory()->create();

        Event::fake([CoverageRebuilt::class]);
        RebuildCoverageJob::dispatchSync($user->id);

        Event::assertDispatched(fn (CoverageRebuilt $event): bool => $event->userId === $user->id);
    }

    #[Test]
    public function it_ignores_activities_without_a_polyline(): void
    {
        $user = User::factory()->create();
        $connection = IntegrationConnection::factory()->for($user)->create();

        SportActivity::factory()->create([
            'user_id' => $user->id,
            'integration_connection_id' => $connection->id,
            'map_polyline' => null,
        ]);

        $count = app(RebuildUserCoverage::class)->handle($user->id);

        $this->assertSame(0, $count);
        $this->assertSame(0, ExploredCell::query()->where('user_id', $user->id)->count());
    }

    #[Test]
    public function it_removes_the_forged_cells_of_the_user_and_keeps_the_derived_ones(): void
    {
        $user = User::factory()->create();
        $this->createRoutedActivity($user);
        app(RebuildUserCoverage::class)->handle($user->id);
        $derivedKeys = $this->cellKeysOf($user);
        ExploredCell::factory()->create(['user_id' => $user->id, 'cell_key' => self::FORGED_CELL_KEY]);

        $count = app(RebuildUserCoverage::class)->handle($user->id);

        $this->assertSame(3, $count);
        $this->assertSame($derivedKeys, $this->cellKeysOf($user));
        $this->assertNotContains(self::FORGED_CELL_KEY, $this->cellKeysOf($user));
    }

    #[Test]
    public function it_leaves_the_cells_of_another_user_untouched(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->createRoutedActivity($user);
        $otherForgedCell = ExploredCell::factory()->create(['user_id' => $other->id, 'cell_key' => self::FORGED_CELL_KEY]);

        app(RebuildUserCoverage::class)->handle($user->id);

        $this->assertDatabaseHas('explored_cells', ['id' => $otherForgedCell->id]);
        $this->assertSame([self::FORGED_CELL_KEY], $this->cellKeysOf($other));
    }

    #[Test]
    public function it_keeps_the_id_and_creation_date_of_the_derived_cells_across_rebuilds(): void
    {
        $user = User::factory()->create();
        $this->createRoutedActivity($user);
        $this->travelTo(Carbon::parse('2026-10-01 10:00:00', 'UTC'));
        app(RebuildUserCoverage::class)->handle($user->id);
        $identityAfterFirstRebuild = $this->identityOfCellsOf($user);

        $this->travelTo(Carbon::parse('2026-10-02 10:00:00', 'UTC'));
        app(RebuildUserCoverage::class)->handle($user->id);

        $this->assertCount(3, $identityAfterFirstRebuild);
        $this->assertSame($identityAfterFirstRebuild, $this->identityOfCellsOf($user));
    }

    #[Test]
    public function it_removes_every_cell_of_a_user_without_a_routed_activity(): void
    {
        $user = User::factory()->create();
        $connection = IntegrationConnection::factory()->for($user)->create();
        SportActivity::factory()->create([
            'user_id' => $user->id,
            'integration_connection_id' => $connection->id,
            'map_polyline' => null,
        ]);
        ExploredCell::factory()->count(2)->sequence(
            ['cell_key' => '100:100'],
            ['cell_key' => '200:200'],
        )->create(['user_id' => $user->id]);

        $count = app(RebuildUserCoverage::class)->handle($user->id);

        $this->assertSame(0, $count);
        $this->assertSame(0, ExploredCell::query()->whereBelongsTo($user)->count());
    }

    #[Test]
    public function it_removes_the_cells_of_a_deleted_activity(): void
    {
        $user = User::factory()->create();
        $activity = $this->createRoutedActivity($user);
        app(RebuildUserCoverage::class)->handle($user->id);
        $activity->delete();

        $count = app(RebuildUserCoverage::class)->handle($user->id);

        $this->assertSame(0, $count);
        $this->assertSame(0, ExploredCell::query()->whereBelongsTo($user)->count());
    }

    #[Test]
    public function it_removes_more_stale_cells_than_one_deletion_chunk_holds(): void
    {
        $user = User::factory()->create();
        $this->createRoutedActivity($user);
        app(RebuildUserCoverage::class)->handle($user->id);
        $derivedKeys = $this->cellKeysOf($user);
        ExploredCell::factory()->count(501)
            ->sequence(fn (Sequence $sequence): array => ['cell_key' => "900000:{$sequence->index}"])
            ->create(['user_id' => $user->id]);

        app(RebuildUserCoverage::class)->handle($user->id);

        $this->assertSame(count($derivedKeys), ExploredCell::query()->whereBelongsTo($user)->count());
    }
}

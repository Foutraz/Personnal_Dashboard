<?php

namespace Tests\Feature\Moto;

use Functional\Moto\Models\MotoRide;
use Functional\Users\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RecordedAtMigrationTest extends TestCase
{
    use RefreshDatabase;

    private Migration $migration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migration = require base_path('functional/moto/database/migrations/2026_10_05_000003_add_recorded_at_to_moto_rides_table.php');
    }

    private function insertRideWithoutRecording(User $user, string $startedAt, ?string $createdAt): string
    {
        $id = (new MotoRide)->newUniqueId();

        MotoRide::query()->insert([
            'id' => $id,
            'user_id' => $user->id,
            'title' => 'Balade',
            'started_at' => $startedAt,
            'duration' => 3600,
            'distance' => 80,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        return $id;
    }

    private function recordedAtOf(string $id): string
    {
        return MotoRide::withTrashed()->findOrFail($id)->recorded_at->toDateTimeString();
    }

    #[Test]
    public function it_backfills_the_recording_instant_from_the_creation_instant(): void
    {
        $user = User::factory()->create();
        $this->migration->down();
        $early = $this->insertRideWithoutRecording($user, '2026-09-01 08:00:00', '2026-09-01 09:00:00');
        $late = $this->insertRideWithoutRecording($user, '2026-09-02 08:00:00', '2026-09-20 18:30:00');

        $this->migration->up();

        $this->assertSame('2026-09-01 09:00:00', $this->recordedAtOf($early));
        $this->assertSame('2026-09-20 18:30:00', $this->recordedAtOf($late));
    }

    #[Test]
    public function it_backfills_a_trashed_ride_too(): void
    {
        $user = User::factory()->create();
        $this->migration->down();
        $id = $this->insertRideWithoutRecording($user, '2026-09-01 08:00:00', '2026-09-03 09:00:00');
        MotoRide::query()->whereKey($id)->update(['deleted_at' => '2026-09-04 09:00:00']);

        $this->migration->up();

        $this->assertSame('2026-09-03 09:00:00', $this->recordedAtOf($id));
    }

    #[Test]
    public function it_stamps_the_migration_instant_on_a_ride_that_has_no_creation_instant(): void
    {
        $this->travelTo(Carbon::parse('2026-10-05 12:00:00', 'UTC'));
        $user = User::factory()->create();
        $this->migration->down();
        $id = $this->insertRideWithoutRecording($user, '2026-09-01 08:00:00', null);

        $this->migration->up();

        $this->assertSame('2026-10-05 12:00:00', $this->recordedAtOf($id));
    }

    #[Test]
    public function it_forbids_a_ride_without_a_recording_instant_once_migrated(): void
    {
        $user = User::factory()->create();

        $this->expectException(QueryException::class);

        $this->insertRideWithoutRecording($user, '2026-09-01 08:00:00', '2026-09-01 09:00:00');
    }

    #[Test]
    public function it_drops_the_recording_column_on_rollback(): void
    {
        $this->assertTrue(Schema::hasColumn('moto_rides', 'recorded_at'));

        $this->migration->down();

        $this->assertFalse(Schema::hasColumn('moto_rides', 'recorded_at'));
    }
}

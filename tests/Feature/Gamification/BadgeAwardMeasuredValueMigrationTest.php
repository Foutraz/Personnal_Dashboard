<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Models\Badge;
use Functional\Gamification\Models\BadgeAward;
use Functional\Users\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BadgeAwardMeasuredValueMigrationTest extends TestCase
{
    use RefreshDatabase;

    private ?Migration $migration = null;

    private ?User $committedUser = null;

    private ?Badge $committedBadge = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migration = require base_path('functional/gamification/database/migrations/2026_10_05_000002_add_measured_value_to_badge_awards_table.php');
    }

    protected function tearDown(): void
    {
        $this->migration?->up();
        $this->committedUser?->forceDelete();
        $this->committedBadge?->delete();

        parent::tearDown();
    }

    #[Test]
    public function it_leaves_the_awards_that_predate_the_column_without_a_snapshot(): void
    {
        $this->migration->down();
        $this->committedUser = User::factory()->create();
        $this->committedBadge = Badge::factory()->create();
        $awardId = (new BadgeAward)->newUniqueId();
        DB::table('badge_awards')->insert([
            'id' => $awardId,
            'user_id' => $this->committedUser->id,
            'badge_id' => $this->committedBadge->id,
            'awarded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->migration->up();

        $this->assertNull(BadgeAward::query()->findOrFail($awardId)->measured_value);
    }

    #[Test]
    public function it_can_run_again_without_moving_a_snapshot(): void
    {
        $award = BadgeAward::factory()->create(['measured_value' => 120.5]);

        $this->migration->up();

        $this->assertSame(120.5, $award->fresh()->measured_value);
        $this->assertTrue(Schema::hasColumn('badge_awards', 'measured_value'));
    }

    #[Test]
    public function it_drops_the_column_when_rolled_back(): void
    {
        $this->migration->down();

        $this->assertFalse(Schema::hasColumn('badge_awards', 'measured_value'));
    }

    #[Test]
    public function it_restores_the_column_when_run_again_after_a_rollback(): void
    {
        $this->migration->down();

        $this->migration->up();

        $this->assertSame('double', Schema::getColumnType('badge_awards', 'measured_value'));
    }
}

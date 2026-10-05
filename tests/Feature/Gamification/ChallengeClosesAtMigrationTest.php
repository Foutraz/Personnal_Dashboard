<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Enums\ChallengeStatus;
use Functional\Gamification\Enums\ChallengeTemplateKey;
use Functional\Gamification\Models\Challenge;
use Functional\Users\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChallengeClosesAtMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const PREVIOUS_WEEK_END = '2026-09-27 22:00:00';

    private const CURRENT_WEEK_END = '2026-10-04 22:00:00';

    private Migration $migration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migration = require base_path('functional/gamification/database/migrations/2026_10_05_000001_add_closes_at_to_challenges_table.php');
    }

    private function insertChallengeWithoutClosing(User $user, string $weekKey, string $endsAt, ChallengeTemplateKey $template = ChallengeTemplateKey::SportDistance): string
    {
        $id = (new Challenge)->newUniqueId();

        Challenge::query()->insert([
            'id' => $id,
            'user_id' => $user->id,
            'week_key' => $weekKey,
            'template_key' => $template->value,
            'domain' => $template->domain()->value,
            'metric' => $template->metric()->value,
            'starts_at' => '2026-09-20 22:00:00',
            'ends_at' => $endsAt,
            'baseline_value' => 25,
            'target_value' => 28,
            'current_value' => 0,
            'xp_reward' => 50,
            'status' => ChallengeStatus::Accepted->value,
            'created_at' => '2026-09-21 06:00:00',
            'updated_at' => '2026-09-21 06:00:00',
        ]);

        return $id;
    }

    private function closesAtOf(string $id): string
    {
        return Challenge::query()->findOrFail($id)->closes_at->toDateTimeString();
    }

    #[Test]
    public function it_backfills_the_closing_from_the_end_of_each_week_and_the_default_grace(): void
    {
        $user = User::factory()->create();
        $this->migration->down();
        $previous = $this->insertChallengeWithoutClosing($user, '2026-W39', self::PREVIOUS_WEEK_END);
        $current = $this->insertChallengeWithoutClosing($user, '2026-W40', self::CURRENT_WEEK_END);

        $this->migration->up();

        $this->assertSame('2026-09-29 22:00:00', $this->closesAtOf($previous));
        $this->assertSame('2026-10-06 22:00:00', $this->closesAtOf($current));
    }

    #[Test]
    public function it_backfills_with_the_grace_configured_when_it_runs(): void
    {
        $user = User::factory()->create();
        $this->migration->down();
        $previous = $this->insertChallengeWithoutClosing($user, '2026-W39', self::PREVIOUS_WEEK_END);
        $current = $this->insertChallengeWithoutClosing($user, '2026-W40', self::CURRENT_WEEK_END);
        config(['gamification.challenges.closing_grace_hours' => 72]);

        $this->migration->up();

        $this->assertSame('2026-09-30 22:00:00', $this->closesAtOf($previous));
        $this->assertSame('2026-10-07 22:00:00', $this->closesAtOf($current));
    }

    #[Test]
    public function it_backfills_every_challenge_sharing_the_same_end(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->migration->down();
        $sport = $this->insertChallengeWithoutClosing($user, '2026-W40', self::CURRENT_WEEK_END);
        $moto = $this->insertChallengeWithoutClosing($user, '2026-W40', self::CURRENT_WEEK_END, ChallengeTemplateKey::MotoDistance);
        $other = $this->insertChallengeWithoutClosing($otherUser, '2026-W40', self::CURRENT_WEEK_END);

        $this->migration->up();

        $this->assertSame('2026-10-06 22:00:00', $this->closesAtOf($sport));
        $this->assertSame('2026-10-06 22:00:00', $this->closesAtOf($moto));
        $this->assertSame('2026-10-06 22:00:00', $this->closesAtOf($other));
    }

    #[Test]
    public function it_refuses_a_challenge_without_closing_once_migrated(): void
    {
        $user = User::factory()->create();

        $this->expectException(QueryException::class);

        $this->insertChallengeWithoutClosing($user, '2026-W40', self::CURRENT_WEEK_END);
    }

    #[Test]
    public function it_can_run_again_after_it_succeeded_without_moving_a_closing(): void
    {
        $user = User::factory()->create();
        $this->migration->down();
        $current = $this->insertChallengeWithoutClosing($user, '2026-W40', self::CURRENT_WEEK_END);
        $this->migration->up();
        config(['gamification.challenges.closing_grace_hours' => 72]);

        $this->migration->up();

        $this->assertSame('2026-10-06 22:00:00', $this->closesAtOf($current));
        $this->assertTrue(Schema::hasColumn('challenges', 'closes_at'));
    }

    #[Test]
    public function it_resumes_after_an_interruption_between_the_column_and_the_backfill(): void
    {
        $user = User::factory()->create();
        $this->migration->down();
        Schema::table('challenges', function (Blueprint $table) {
            $table->timestamp('closes_at')->nullable()->after('ends_at');
        });
        $filled = $this->insertChallengeWithoutClosing($user, '2026-W39', self::PREVIOUS_WEEK_END);
        $empty = $this->insertChallengeWithoutClosing($user, '2026-W40', self::CURRENT_WEEK_END);
        Challenge::query()->whereKey($filled)->update(['closes_at' => '2026-09-28 12:00:00']);

        $this->migration->up();

        $this->assertSame('2026-09-28 12:00:00', $this->closesAtOf($filled));
        $this->assertSame('2026-10-06 22:00:00', $this->closesAtOf($empty));
    }

    #[Test]
    public function it_drops_the_closing_column_when_rolled_back(): void
    {
        $this->migration->down();

        $this->assertFalse(Schema::hasColumn('challenges', 'closes_at'));
    }
}

<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Challenges\States\ProposedChallengeState;
use Functional\Gamification\Enums\ChallengeStatus;
use Functional\Gamification\Enums\ChallengeTemplateKey;
use Functional\Gamification\Enums\GamificationDomain;
use Functional\Gamification\Models\Challenge;
use Functional\Gamification\Services\GamificationCalendar;
use Functional\Goals\Enums\GoalMetric;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChallengeModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-01 10:00:00', 'UTC'));
    }

    #[Test]
    public function it_persists_a_challenge_through_its_factory_with_a_lowercase_ulid_and_enum_casts(): void
    {
        $challenge = Challenge::factory()->create(['target_value' => 28])->fresh();

        $this->assertTrue(Str::isUlid($challenge->id));
        $this->assertSame(strtolower($challenge->id), $challenge->id);
        $this->assertSame(ChallengeTemplateKey::SportDistance, $challenge->template_key);
        $this->assertSame(GamificationDomain::Sport, $challenge->domain);
        $this->assertSame(GoalMetric::SportDistance, $challenge->metric);
        $this->assertSame(ChallengeStatus::Proposed, $challenge->status);
        $this->assertSame('28.00', $challenge->target_value);
        $this->assertSame('0.00', $challenge->current_value);
        $this->assertIsInt($challenge->xp_reward);
        $this->assertNull($challenge->accepted_at);
        $this->assertNull($challenge->resolved_at);
        $this->assertTrue($challenge->user->is(User::query()->findOrFail($challenge->user_id)));
    }

    #[Test]
    public function it_proposes_a_challenge_on_the_current_week_by_default(): void
    {
        $challenge = Challenge::factory()->create()->fresh();
        $currentWeek = app(GamificationCalendar::class)->currentWeek();

        $this->assertSame('2026-W40', $challenge->week_key);
        $this->assertSame($currentWeek->startsAt->toDateTimeString(), $challenge->starts_at->toDateTimeString());
        $this->assertSame($currentWeek->endsAt->toDateTimeString(), $challenge->ends_at->toDateTimeString());
    }

    #[Test]
    public function it_builds_a_challenge_for_a_given_week(): void
    {
        $week = app(GamificationCalendar::class)->currentWeek()->previous();

        $challenge = Challenge::factory()->forWeek($week)->create()->fresh();

        $this->assertSame('2026-W39', $challenge->week_key);
        $this->assertSame('2026-09-20 22:00:00', $challenge->starts_at->toDateTimeString());
        $this->assertSame('2026-09-27 22:00:00', $challenge->ends_at->toDateTimeString());
    }

    #[Test]
    public function it_builds_a_challenge_for_a_given_template_with_its_domain_and_metric(): void
    {
        $challenge = Challenge::factory()->forTemplate(ChallengeTemplateKey::MotoRideCount)->create()->fresh();

        $this->assertSame(ChallengeTemplateKey::MotoRideCount, $challenge->template_key);
        $this->assertSame(GamificationDomain::Moto, $challenge->domain);
        $this->assertSame(GoalMetric::MotoRideCount, $challenge->metric);
    }

    #[Test]
    public function it_builds_an_accepted_challenge_with_its_acceptance_moment(): void
    {
        $challenge = Challenge::factory()->accepted()->create()->fresh();

        $this->assertSame(ChallengeStatus::Accepted, $challenge->status);
        $this->assertSame('2026-10-01 10:00:00', $challenge->accepted_at->toDateTimeString());
        $this->assertNull($challenge->resolved_at);
    }

    #[Test]
    public function it_builds_a_completed_challenge_that_reached_its_target(): void
    {
        $challenge = Challenge::factory()->completed()->create(['target_value' => 28])->fresh();

        $this->assertSame(ChallengeStatus::Completed, $challenge->status);
        $this->assertSame('2026-10-01 10:00:00', $challenge->accepted_at->toDateTimeString());
        $this->assertSame('2026-10-01 10:00:00', $challenge->resolved_at->toDateTimeString());
        $this->assertSame('28.00', $challenge->current_value);
    }

    #[Test]
    public function it_builds_a_failed_challenge_that_missed_its_target(): void
    {
        $challenge = Challenge::factory()->failed()->create(['target_value' => 28])->fresh();

        $this->assertSame(ChallengeStatus::Failed, $challenge->status);
        $this->assertNotNull($challenge->accepted_at);
        $this->assertNotNull($challenge->resolved_at);
        $this->assertLessThan(28, (float) $challenge->current_value);
    }

    #[Test]
    public function it_builds_a_declined_challenge_that_was_never_accepted(): void
    {
        $challenge = Challenge::factory()->declined()->create()->fresh();

        $this->assertSame(ChallengeStatus::Declined, $challenge->status);
        $this->assertNull($challenge->accepted_at);
        $this->assertNotNull($challenge->resolved_at);
    }

    #[Test]
    public function it_builds_an_expired_challenge_that_was_never_accepted(): void
    {
        $challenge = Challenge::factory()->expired()->create()->fresh();

        $this->assertSame(ChallengeStatus::Expired, $challenge->status);
        $this->assertNull($challenge->accepted_at);
        $this->assertNotNull($challenge->resolved_at);
    }

    #[Test]
    public function it_ignores_a_duplicate_challenge_for_the_same_user_week_and_template(): void
    {
        $challenge = Challenge::factory()->create();

        $inserted = $this->insertChallengeRow($challenge->user_id, '2026-W40', ChallengeTemplateKey::SportDistance);

        $this->assertSame(0, $inserted);
        $this->assertSame(1, Challenge::query()->count());
    }

    #[Test]
    public function it_accepts_the_same_template_on_another_week(): void
    {
        $challenge = Challenge::factory()->create();

        $inserted = $this->insertChallengeRow($challenge->user_id, '2026-W41', ChallengeTemplateKey::SportDistance);

        $this->assertSame(1, $inserted);
        $this->assertSame(2, Challenge::query()->count());
    }

    #[Test]
    public function it_exposes_the_proposed_state_of_a_new_challenge(): void
    {
        $challenge = Challenge::factory()->create();

        $this->assertInstanceOf(ProposedChallengeState::class, $challenge->state());
    }

    #[Test]
    public function it_deletes_the_challenges_of_a_deleted_user_and_keeps_the_others(): void
    {
        $challenge = Challenge::factory()->create();
        $otherChallenge = Challenge::factory()->create();

        $challenge->user->delete();

        $this->assertFalse(Challenge::query()->whereKey($challenge->id)->exists());
        $this->assertTrue(Challenge::query()->whereKey($otherChallenge->id)->exists());
    }

    private function insertChallengeRow(string $userId, string $weekKey, ChallengeTemplateKey $template): int
    {
        $currentWeek = app(GamificationCalendar::class)->currentWeek();

        return DB::table('challenges')->insertOrIgnore([
            'id' => (new Challenge)->newUniqueId(),
            'user_id' => $userId,
            'week_key' => $weekKey,
            'template_key' => $template->value,
            'domain' => $template->domain()->value,
            'metric' => $template->metric()->value,
            'starts_at' => $currentWeek->startsAt,
            'ends_at' => $currentWeek->endsAt,
            'baseline_value' => 25,
            'target_value' => 28,
            'current_value' => 0,
            'xp_reward' => 50,
            'status' => ChallengeStatus::Proposed->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

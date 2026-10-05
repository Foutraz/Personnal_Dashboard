<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Actions\TransitionChallenge;
use Functional\Gamification\Enums\ChallengeStatus;
use Functional\Gamification\Exceptions\IllegalChallengeTransitionException;
use Functional\Gamification\Exceptions\StaleChallengeStatusException;
use Functional\Gamification\Models\Challenge;
use Functional\Gamification\Services\Dto\ChallengeProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransitionChallengeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-01 10:00:00', 'UTC'));
    }

    #[Test]
    public function it_accepts_a_proposed_challenge_and_stamps_the_acceptance(): void
    {
        $challenge = Challenge::factory()->create();

        new TransitionChallenge()->handle($challenge, $challenge->state()->accept());

        $persisted = $challenge->fresh();
        $this->assertSame(ChallengeStatus::Accepted, $persisted->status);
        $this->assertSame('2026-10-01 10:00:00', $persisted->accepted_at->toDateTimeString());
        $this->assertNull($persisted->resolved_at);
        $this->assertSame('0.00', $persisted->current_value);
    }

    #[Test]
    public function it_synchronises_the_loaded_model_with_the_persisted_transition(): void
    {
        $challenge = Challenge::factory()->create();

        new TransitionChallenge()->handle($challenge, $challenge->state()->accept());

        $this->assertSame(ChallengeStatus::Accepted, $challenge->status);
        $this->assertSame('2026-10-01 10:00:00', $challenge->accepted_at->toDateTimeString());
        $this->assertFalse($challenge->isDirty());
    }

    #[Test]
    public function it_completes_an_accepted_challenge_with_its_measurement_and_keeps_the_acceptance_moment(): void
    {
        $challenge = Challenge::factory()->create();
        $transition = new TransitionChallenge;
        $transition->handle($challenge, $challenge->state()->accept());
        $this->travelTo(Carbon::parse('2026-10-03 18:30:00', 'UTC'));

        $transition->handle($challenge, $challenge->state()->complete(), 30.0);

        $persisted = $challenge->fresh();
        $this->assertSame(ChallengeStatus::Completed, $persisted->status);
        $this->assertSame('2026-10-01 10:00:00', $persisted->accepted_at->toDateTimeString());
        $this->assertSame('2026-10-03 18:30:00', $persisted->resolved_at->toDateTimeString());
        $this->assertSame('30.00', $persisted->current_value);
    }

    #[Test]
    public function it_fails_an_accepted_challenge_with_the_value_reached_at_the_closing(): void
    {
        $challenge = Challenge::factory()->accepted()->create(['target_value' => 28]);

        new TransitionChallenge()->handle($challenge, $challenge->state()->fail(), 12.5);

        $persisted = $challenge->fresh();
        $this->assertSame(ChallengeStatus::Failed, $persisted->status);
        $this->assertSame('12.50', $persisted->current_value);
        $this->assertNotNull($persisted->resolved_at);
    }

    #[Test]
    public function it_declines_a_proposed_challenge_without_stamping_an_acceptance(): void
    {
        $challenge = Challenge::factory()->create();

        new TransitionChallenge()->handle($challenge, $challenge->state()->decline());

        $persisted = $challenge->fresh();
        $this->assertSame(ChallengeStatus::Declined, $persisted->status);
        $this->assertNull($persisted->accepted_at);
        $this->assertSame('2026-10-01 10:00:00', $persisted->resolved_at->toDateTimeString());
    }

    #[Test]
    public function it_expires_a_proposed_challenge(): void
    {
        $challenge = Challenge::factory()->create();

        new TransitionChallenge()->handle($challenge, $challenge->state()->expire());

        $persisted = $challenge->fresh();
        $this->assertSame(ChallengeStatus::Expired, $persisted->status);
        $this->assertNull($persisted->accepted_at);
        $this->assertNotNull($persisted->resolved_at);
    }

    #[Test]
    public function it_keeps_the_last_measurement_when_a_transition_carries_none(): void
    {
        $challenge = Challenge::factory()->accepted()->create(['current_value' => 7.5]);

        new TransitionChallenge()->handle($challenge, $challenge->state()->fail());

        $this->assertSame('7.50', $challenge->fresh()->current_value);
    }

    #[Test]
    public function it_refuses_to_overwrite_a_status_changed_since_the_challenge_was_loaded(): void
    {
        $challenge = $this->challengeDeclinedBehindTheLoadedModel();

        $this->expectException(StaleChallengeStatusException::class);

        new TransitionChallenge()->handle($challenge, $challenge->state()->accept());
    }

    #[Test]
    public function it_leaves_the_persisted_challenge_untouched_when_the_status_is_stale(): void
    {
        $challenge = $this->challengeDeclinedBehindTheLoadedModel();

        $this->assertThrows(
            fn () => new TransitionChallenge()->handle($challenge, $challenge->state()->accept()),
            StaleChallengeStatusException::class,
        );

        $persisted = $challenge->fresh();
        $this->assertSame(ChallengeStatus::Declined, $persisted->status);
        $this->assertNull($persisted->accepted_at);
    }

    #[Test]
    public function it_leaves_the_loaded_model_untouched_when_the_status_is_stale(): void
    {
        $challenge = $this->challengeDeclinedBehindTheLoadedModel();

        $this->assertThrows(
            fn () => new TransitionChallenge()->handle($challenge, $challenge->state()->accept()),
            StaleChallengeStatusException::class,
        );

        $this->assertSame(ChallengeStatus::Proposed, $challenge->status);
        $this->assertNull($challenge->accepted_at);
    }

    #[Test]
    public function it_names_the_challenge_and_the_expected_status_in_the_stale_exception(): void
    {
        $challenge = $this->challengeDeclinedBehindTheLoadedModel();

        $this->assertThrows(
            fn () => new TransitionChallenge()->handle($challenge, $challenge->state()->accept()),
            fn (StaleChallengeStatusException $stale): bool => $stale->challengeId === $challenge->id
                && $stale->expectedStatus === ChallengeStatus::Proposed,
        );
    }

    #[Test]
    public function it_refuses_an_illegal_transition_before_touching_the_database(): void
    {
        $challenge = Challenge::factory()->completed()->create();

        $this->expectException(IllegalChallengeTransitionException::class);

        new TransitionChallenge()->handle($challenge, $challenge->state()->accept());
    }

    #[Test]
    public function it_persists_nothing_when_the_evolved_state_keeps_the_status_of_an_accepted_challenge(): void
    {
        $challenge = Challenge::factory()->accepted()->create(['accepted_at' => Carbon::parse('2026-09-29 08:00:00', 'UTC'), 'current_value' => 7.5]);
        $before = $challenge->fresh();
        $this->travelTo(Carbon::parse('2026-10-03 18:30:00', 'UTC'));
        $unchanged = $challenge->state()->evolve(new ChallengeProgress(targetReached: false, weekEnded: false, gracePassed: false));

        new TransitionChallenge()->handle($challenge, $unchanged, 12.0);

        $persisted = $challenge->fresh();
        $this->assertSame(ChallengeStatus::Accepted, $persisted->status);
        $this->assertSame('2026-09-29 08:00:00', $persisted->accepted_at->toDateTimeString());
        $this->assertNull($persisted->resolved_at);
        $this->assertSame('7.50', $persisted->current_value);
        $this->assertTrue($before->updated_at->equalTo($persisted->updated_at));
    }

    #[Test]
    public function it_persists_nothing_when_the_evolved_state_keeps_the_status_of_a_proposed_challenge(): void
    {
        $challenge = Challenge::factory()->create();
        $before = $challenge->fresh();
        $this->travelTo(Carbon::parse('2026-10-03 18:30:00', 'UTC'));
        $unchanged = $challenge->state()->evolve(new ChallengeProgress(targetReached: true, weekEnded: false, gracePassed: false));

        new TransitionChallenge()->handle($challenge, $unchanged);

        $persisted = $challenge->fresh();
        $this->assertSame(ChallengeStatus::Proposed, $persisted->status);
        $this->assertNull($persisted->accepted_at);
        $this->assertNull($persisted->resolved_at);
        $this->assertTrue($before->updated_at->equalTo($persisted->updated_at));
    }

    #[Test]
    public function it_issues_no_query_when_the_next_state_keeps_the_status(): void
    {
        $challenge = Challenge::factory()->accepted()->create();
        DB::enableQueryLog();

        new TransitionChallenge()->handle($challenge, $challenge->state());

        $this->assertSame([], DB::getQueryLog());
    }

    private function challengeDeclinedBehindTheLoadedModel(): Challenge
    {
        $challenge = Challenge::factory()->create();
        Challenge::query()->whereKey($challenge->id)->update(['status' => ChallengeStatus::Declined]);

        return $challenge;
    }
}

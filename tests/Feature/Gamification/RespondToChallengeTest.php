<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Actions\RespondToChallenge;
use Functional\Gamification\Enums\ChallengeStatus;
use Functional\Gamification\Exceptions\ChallengeNotRespondableException;
use Functional\Gamification\Jobs\ProcessUserGamificationJob;
use Functional\Gamification\Models\Challenge;
use Functional\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RespondToChallengeTest extends TestCase
{
    use RefreshDatabase;

    private const WEEK_END = '2026-10-04 22:00:00';

    private RespondToChallenge $respondToChallenge;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-01 10:00:00', 'UTC'));
        Queue::fake();
        $this->respondToChallenge = app(RespondToChallenge::class);
    }

    #[Test]
    public function it_accepts_a_proposed_challenge_and_stamps_the_acceptance(): void
    {
        $challenge = Challenge::factory()->create();

        $this->respondToChallenge->accept($challenge);

        $persisted = $challenge->fresh();
        $this->assertSame(ChallengeStatus::Accepted, $persisted->status);
        $this->assertSame('2026-10-01 10:00:00', $persisted->accepted_at->toDateTimeString());
        $this->assertNull($persisted->resolved_at);
    }

    #[Test]
    public function it_dispatches_the_owners_gamification_run_from_the_previous_day_after_an_acceptance(): void
    {
        $challenge = Challenge::factory()->create();

        $this->respondToChallenge->accept($challenge);

        Queue::assertPushed(ProcessUserGamificationJob::class, 1);
        Queue::assertPushed(fn (ProcessUserGamificationJob $job): bool => $job->userId === $challenge->user_id
            && $job->since->toDateTimeString() === '2026-09-30 10:00:00');
    }

    #[Test]
    public function it_declines_a_proposed_challenge_and_stamps_the_resolution(): void
    {
        $challenge = Challenge::factory()->create();

        $this->respondToChallenge->decline($challenge);

        $persisted = $challenge->fresh();
        $this->assertSame(ChallengeStatus::Declined, $persisted->status);
        $this->assertSame('2026-10-01 10:00:00', $persisted->resolved_at->toDateTimeString());
        $this->assertNull($persisted->accepted_at);
    }

    #[Test]
    public function it_dispatches_nothing_after_a_decline(): void
    {
        $challenge = Challenge::factory()->create();

        $this->respondToChallenge->decline($challenge);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_refuses_accepting_an_already_accepted_challenge(): void
    {
        $this->app->setLocale('fr');
        $challenge = Challenge::factory()->accepted()->create();

        $this->assertThrows(
            fn () => $this->respondToChallenge->accept($challenge),
            ChallengeNotRespondableException::class,
            'Ce défi ne peut plus être accepté ni refusé.',
        );

        $this->assertSame(ChallengeStatus::Accepted, $challenge->fresh()->status);
        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_refuses_declining_an_already_declined_challenge(): void
    {
        $challenge = Challenge::factory()->declined()->create();

        $this->assertThrows(
            fn () => $this->respondToChallenge->decline($challenge),
            ChallengeNotRespondableException::class,
        );

        $this->assertSame(ChallengeStatus::Declined, $challenge->fresh()->status);
    }

    #[Test]
    public function it_refuses_accepting_a_challenge_whose_week_is_over(): void
    {
        $challenge = Challenge::factory()->create();
        $this->travelTo(Carbon::parse(self::WEEK_END, 'UTC'));

        $this->assertThrows(
            fn () => $this->respondToChallenge->accept($challenge),
            ChallengeNotRespondableException::class,
        );

        $this->assertSame(ChallengeStatus::Proposed, $challenge->fresh()->status);
        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_refuses_declining_a_challenge_whose_week_is_over(): void
    {
        $challenge = Challenge::factory()->create();
        $this->travelTo(Carbon::parse(self::WEEK_END, 'UTC'));

        $this->assertThrows(
            fn () => $this->respondToChallenge->decline($challenge),
            ChallengeNotRespondableException::class,
        );

        $this->assertSame(ChallengeStatus::Proposed, $challenge->fresh()->status);
    }

    #[Test]
    public function it_is_a_conflict_http_exception(): void
    {
        $this->assertSame(Response::HTTP_CONFLICT, (new ChallengeNotRespondableException)->getStatusCode());
    }

    #[Test]
    public function it_words_the_refusal_in_french_and_in_english(): void
    {
        $this->app->setLocale('fr');
        $this->assertSame('Ce défi ne peut plus être accepté ni refusé.', (new ChallengeNotRespondableException)->getMessage());

        $this->app->setLocale('en');
        $this->assertSame('This challenge can no longer be accepted or skipped.', (new ChallengeNotRespondableException)->getMessage());
    }

    #[Test]
    public function it_is_respondable_while_a_proposed_challenge_week_is_running(): void
    {
        $challenge = Challenge::factory()->create();

        $this->assertTrue($this->respondToChallenge->isRespondable($challenge));
    }

    #[Test]
    public function it_is_not_respondable_once_the_week_has_ended(): void
    {
        $challenge = Challenge::factory()->create();
        $this->travelTo(Carbon::parse(self::WEEK_END, 'UTC'));

        $this->assertFalse($this->respondToChallenge->isRespondable($challenge));
    }

    #[Test]
    public function it_is_still_respondable_one_second_before_the_week_ends(): void
    {
        $challenge = Challenge::factory()->create();
        $this->travelTo(Carbon::parse(self::WEEK_END, 'UTC')->subSecond());

        $this->assertTrue($this->respondToChallenge->isRespondable($challenge));
    }

    #[Test]
    public function it_is_not_respondable_for_a_challenge_that_no_longer_awaits_a_response(): void
    {
        $this->assertFalse($this->respondToChallenge->isRespondable(Challenge::factory()->accepted()->create()));
        $this->assertFalse($this->respondToChallenge->isRespondable(Challenge::factory()->declined()->create()));
        $this->assertFalse($this->respondToChallenge->isRespondable(Challenge::factory()->completed()->create()));
        $this->assertFalse($this->respondToChallenge->isRespondable(Challenge::factory()->failed()->create()));
        $this->assertFalse($this->respondToChallenge->isRespondable(Challenge::factory()->expired()->create()));
    }

    #[Test]
    public function it_authorises_the_owner_to_respond(): void
    {
        $challenge = Challenge::factory()->create();

        $this->assertTrue($challenge->user->can('respond', $challenge));
    }

    #[Test]
    public function it_denies_responding_to_the_challenge_of_another_user(): void
    {
        $challenge = Challenge::factory()->create();

        $this->assertFalse(User::factory()->create()->can('respond', $challenge));
    }
}

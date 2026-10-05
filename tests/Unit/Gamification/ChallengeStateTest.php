<?php

namespace Tests\Unit\Gamification;

use DomainException;
use Functional\Gamification\Challenges\States\AcceptedChallengeState;
use Functional\Gamification\Challenges\States\ChallengeState;
use Functional\Gamification\Challenges\States\ChallengeStateFactory;
use Functional\Gamification\Challenges\States\CompletedChallengeState;
use Functional\Gamification\Challenges\States\DeclinedChallengeState;
use Functional\Gamification\Challenges\States\ExpiredChallengeState;
use Functional\Gamification\Challenges\States\FailedChallengeState;
use Functional\Gamification\Challenges\States\ProposedChallengeState;
use Functional\Gamification\Enums\ChallengeStatus;
use Functional\Gamification\Exceptions\IllegalChallengeTransitionException;
use Functional\Gamification\Services\Dto\ChallengeProgress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ChallengeStateTest extends TestCase
{
    #[Test]
    public function it_accepts_a_proposed_challenge(): void
    {
        $this->assertInstanceOf(AcceptedChallengeState::class, new ProposedChallengeState()->accept());
    }

    #[Test]
    public function it_declines_a_proposed_challenge(): void
    {
        $this->assertInstanceOf(DeclinedChallengeState::class, new ProposedChallengeState()->decline());
    }

    #[Test]
    public function it_expires_a_proposed_challenge(): void
    {
        $this->assertInstanceOf(ExpiredChallengeState::class, new ProposedChallengeState()->expire());
    }

    #[Test]
    #[DataProvider('operationsRefusedByProposed')]
    public function it_refuses_the_operations_a_proposed_challenge_cannot_take(string $operation, callable $call): void
    {
        $this->assertRefusesWithOperation(new ProposedChallengeState, $operation, $call);
    }

    #[Test]
    public function it_completes_an_accepted_challenge(): void
    {
        $this->assertInstanceOf(CompletedChallengeState::class, new AcceptedChallengeState()->complete());
    }

    #[Test]
    public function it_fails_an_accepted_challenge(): void
    {
        $this->assertInstanceOf(FailedChallengeState::class, new AcceptedChallengeState()->fail());
    }

    #[Test]
    #[DataProvider('operationsRefusedByAccepted')]
    public function it_refuses_the_operations_an_accepted_challenge_cannot_take(string $operation, callable $call): void
    {
        $this->assertRefusesWithOperation(new AcceptedChallengeState, $operation, $call);
    }

    #[Test]
    #[DataProvider('terminalRefusals')]
    public function it_refuses_every_operation_on_a_terminal_challenge(ChallengeStatus $status, string $operation, callable $call): void
    {
        $this->assertRefusesWithOperation(ChallengeStateFactory::fromStatus($status), $operation, $call);
    }

    #[Test]
    public function it_names_the_state_and_the_operation_in_the_illegal_transition(): void
    {
        $exception = IllegalChallengeTransitionException::for(new CompletedChallengeState, 'fail');

        $this->assertInstanceOf(DomainException::class, $exception);
        $this->assertSame(ChallengeStatus::Completed, $exception->status);
        $this->assertSame('fail', $exception->operation);
        $this->assertStringContainsString('fail', $exception->getMessage());
        $this->assertStringContainsString('completed', $exception->getMessage());
    }

    #[Test]
    #[DataProvider('evolutions')]
    public function it_evolves_a_challenge_with_the_time_rule_of_its_state(ChallengeStatus $status, ChallengeProgress $progress, string $expectedState): void
    {
        $this->assertInstanceOf($expectedState, ChallengeStateFactory::fromStatus($status)->evolve($progress));
    }

    #[Test]
    #[DataProvider('everyStatus')]
    public function it_rebuilds_the_state_matching_the_status(ChallengeStatus $status, string $expectedState): void
    {
        $state = ChallengeStateFactory::fromStatus($status);

        $this->assertSame($status, $state->status());
        $this->assertInstanceOf($expectedState, $state);
    }

    #[Test]
    #[DataProvider('behaviourFlags')]
    public function it_exposes_the_behaviour_flags_of_each_state(ChallengeStatus $status, bool $awaitsResponse, bool $isCommitment, bool $isTerminal): void
    {
        $state = ChallengeStateFactory::fromStatus($status);

        $this->assertSame($awaitsResponse, $state->awaitsResponse());
        $this->assertSame($isCommitment, $state->isCommitment());
        $this->assertSame($isTerminal, $state->isTerminal());
    }

    /**
     * @return iterable<string, array{string, callable(ChallengeState): ChallengeState}>
     */
    public static function operationsRefusedByProposed(): iterable
    {
        yield 'complete' => ['complete', fn (ChallengeState $state): ChallengeState => $state->complete()];
        yield 'fail' => ['fail', fn (ChallengeState $state): ChallengeState => $state->fail()];
    }

    /**
     * @return iterable<string, array{string, callable(ChallengeState): ChallengeState}>
     */
    public static function operationsRefusedByAccepted(): iterable
    {
        yield 'accept' => ['accept', fn (ChallengeState $state): ChallengeState => $state->accept()];
        yield 'decline' => ['decline', fn (ChallengeState $state): ChallengeState => $state->decline()];
        yield 'expire' => ['expire', fn (ChallengeState $state): ChallengeState => $state->expire()];
    }

    /**
     * @return iterable<string, array{ChallengeStatus, string, callable(ChallengeState): ChallengeState}>
     */
    public static function terminalRefusals(): iterable
    {
        $terminalStatuses = [
            ChallengeStatus::Completed,
            ChallengeStatus::Failed,
            ChallengeStatus::Declined,
            ChallengeStatus::Expired,
        ];

        $operations = [
            'accept' => fn (ChallengeState $state): ChallengeState => $state->accept(),
            'decline' => fn (ChallengeState $state): ChallengeState => $state->decline(),
            'complete' => fn (ChallengeState $state): ChallengeState => $state->complete(),
            'fail' => fn (ChallengeState $state): ChallengeState => $state->fail(),
            'expire' => fn (ChallengeState $state): ChallengeState => $state->expire(),
            'evolve' => fn (ChallengeState $state): ChallengeState => $state->evolve(new ChallengeProgress(true, true, true)),
        ];

        foreach ($terminalStatuses as $terminalStatus) {
            foreach ($operations as $operation => $call) {
                yield "{$terminalStatus->value} {$operation}" => [$terminalStatus, $operation, $call];
            }
        }
    }

    /**
     * @return iterable<string, array{ChallengeStatus, ChallengeProgress, class-string<ChallengeState>}>
     */
    public static function evolutions(): iterable
    {
        yield 'proposed with the target reached during the week stays proposed' => [
            ChallengeStatus::Proposed,
            new ChallengeProgress(targetReached: true, weekEnded: false, gracePassed: false),
            ProposedChallengeState::class,
        ];
        yield 'proposed with the target missed during the week stays proposed' => [
            ChallengeStatus::Proposed,
            new ChallengeProgress(targetReached: false, weekEnded: false, gracePassed: false),
            ProposedChallengeState::class,
        ];
        yield 'proposed once the week is over expires' => [
            ChallengeStatus::Proposed,
            new ChallengeProgress(targetReached: false, weekEnded: true, gracePassed: false),
            ExpiredChallengeState::class,
        ];
        yield 'proposed with the target reached once the week is over expires' => [
            ChallengeStatus::Proposed,
            new ChallengeProgress(targetReached: true, weekEnded: true, gracePassed: false),
            ExpiredChallengeState::class,
        ];
        yield 'accepted with the target reached completes during the week' => [
            ChallengeStatus::Accepted,
            new ChallengeProgress(targetReached: true, weekEnded: false, gracePassed: false),
            CompletedChallengeState::class,
        ];
        yield 'accepted with the target reached completes even after the grace' => [
            ChallengeStatus::Accepted,
            new ChallengeProgress(targetReached: true, weekEnded: true, gracePassed: true),
            CompletedChallengeState::class,
        ];
        yield 'accepted with the target missed during the week stays accepted' => [
            ChallengeStatus::Accepted,
            new ChallengeProgress(targetReached: false, weekEnded: false, gracePassed: false),
            AcceptedChallengeState::class,
        ];
        yield 'accepted with the target missed inside the grace stays accepted' => [
            ChallengeStatus::Accepted,
            new ChallengeProgress(targetReached: false, weekEnded: true, gracePassed: false),
            AcceptedChallengeState::class,
        ];
        yield 'accepted with the target missed after the grace fails' => [
            ChallengeStatus::Accepted,
            new ChallengeProgress(targetReached: false, weekEnded: true, gracePassed: true),
            FailedChallengeState::class,
        ];
    }

    /**
     * @return iterable<string, array{ChallengeStatus, class-string<ChallengeState>}>
     */
    public static function everyStatus(): iterable
    {
        yield 'proposed' => [ChallengeStatus::Proposed, ProposedChallengeState::class];
        yield 'accepted' => [ChallengeStatus::Accepted, AcceptedChallengeState::class];
        yield 'completed' => [ChallengeStatus::Completed, CompletedChallengeState::class];
        yield 'failed' => [ChallengeStatus::Failed, FailedChallengeState::class];
        yield 'declined' => [ChallengeStatus::Declined, DeclinedChallengeState::class];
        yield 'expired' => [ChallengeStatus::Expired, ExpiredChallengeState::class];
    }

    /**
     * @return iterable<string, array{ChallengeStatus, bool, bool, bool}>
     */
    public static function behaviourFlags(): iterable
    {
        yield 'proposed' => [ChallengeStatus::Proposed, true, false, false];
        yield 'accepted' => [ChallengeStatus::Accepted, false, true, false];
        yield 'completed' => [ChallengeStatus::Completed, false, false, true];
        yield 'failed' => [ChallengeStatus::Failed, false, false, true];
        yield 'declined' => [ChallengeStatus::Declined, false, false, true];
        yield 'expired' => [ChallengeStatus::Expired, false, false, true];
    }

    /**
     * @param  callable(ChallengeState): ChallengeState  $call
     */
    private function assertRefusesWithOperation(ChallengeState $state, string $operation, callable $call): void
    {
        $this->expectException(IllegalChallengeTransitionException::class);
        $this->expectExceptionMessage($operation);

        $call($state);
    }
}

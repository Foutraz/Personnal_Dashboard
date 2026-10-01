<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Challenges\States\ChallengeStateFactory;
use Functional\Gamification\Enums\ChallengeStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChallengeStatusTest extends TestCase
{
    #[Test]
    public function it_lists_the_open_statuses_from_the_state_factory(): void
    {
        $this->assertSame([ChallengeStatus::Proposed, ChallengeStatus::Accepted], ChallengeStatus::open());
    }

    #[Test]
    public function it_never_lists_a_terminal_status_as_open(): void
    {
        foreach (ChallengeStatus::open() as $openStatus) {
            $this->assertFalse(ChallengeStateFactory::fromStatus($openStatus)->isTerminal());
        }
    }

    #[Test]
    public function it_labels_every_status_in_french(): void
    {
        $this->app->setLocale('fr');

        $this->assertSame('Proposé', ChallengeStatus::Proposed->label());
        $this->assertSame('En cours', ChallengeStatus::Accepted->label());
        $this->assertSame('Réussi', ChallengeStatus::Completed->label());
        $this->assertSame('Manqué', ChallengeStatus::Failed->label());
        $this->assertSame('Passé', ChallengeStatus::Declined->label());
        $this->assertSame('Expiré', ChallengeStatus::Expired->label());
    }

    #[Test]
    public function it_labels_every_status_in_english(): void
    {
        $this->app->setLocale('en');

        $this->assertSame('Proposed', ChallengeStatus::Proposed->label());
        $this->assertSame('In progress', ChallengeStatus::Accepted->label());
        $this->assertSame('Completed', ChallengeStatus::Completed->label());
        $this->assertSame('Missed', ChallengeStatus::Failed->label());
        $this->assertSame('Skipped', ChallengeStatus::Declined->label());
        $this->assertSame('Expired', ChallengeStatus::Expired->label());
    }

    #[Test]
    public function it_counts_only_the_accepted_and_the_resolved_challenges_as_engaged(): void
    {
        $engaged = array_filter(ChallengeStatus::cases(), fn (ChallengeStatus $status): bool => $status->isEngaged());

        $this->assertEqualsCanonicalizing(
            [ChallengeStatus::Accepted, ChallengeStatus::Completed, ChallengeStatus::Failed],
            array_values($engaged),
        );
    }

    #[Test]
    #[DataProvider('chipClasses')]
    public function it_gives_each_status_its_chip_classes(ChallengeStatus $status, string $expectedClasses): void
    {
        $this->assertSame($expectedClasses, $status->chipClass());
    }

    /**
     * @return iterable<string, array{ChallengeStatus, string}>
     */
    public static function chipClasses(): iterable
    {
        yield 'proposed' => [ChallengeStatus::Proposed, 'border-hairline bg-violet-soft text-violet'];
        yield 'accepted' => [ChallengeStatus::Accepted, 'border-hairline bg-cyan-soft text-cyan'];
        yield 'completed' => [ChallengeStatus::Completed, 'border-hairline bg-lime-soft text-lime'];
        yield 'failed' => [ChallengeStatus::Failed, 'border-hairline bg-surface-2 text-muted'];
        yield 'declined' => [ChallengeStatus::Declined, 'border-hairline bg-surface text-faint'];
        yield 'expired' => [ChallengeStatus::Expired, 'border-hairline bg-surface text-faint'];
    }
}

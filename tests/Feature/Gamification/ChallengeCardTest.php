<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Enums\ChallengeStatus;
use Functional\Gamification\Enums\ChallengeTemplateKey;
use Functional\Gamification\Models\Challenge;
use Functional\Gamification\Services\Dto\ChallengeCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChallengeCardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-01 10:00:00', 'UTC'));
        $this->app->setLocale('fr');
    }

    #[Test]
    public function it_computes_the_exact_and_the_rounded_percentage_of_the_progress(): void
    {
        $card = $this->card(['current_value' => 12, 'target_value' => 28]);

        $this->assertEqualsWithDelta(42.857, $card->percentage(), 0.001);
        $this->assertSame(43, $card->roundedPercentage());
    }

    #[Test]
    public function it_caps_the_percentage_at_one_hundred_once_the_target_is_exceeded(): void
    {
        $card = $this->card(['current_value' => 30, 'target_value' => 28]);

        $this->assertSame(100.0, $card->percentage());
        $this->assertSame(100, $card->roundedPercentage());
    }

    #[Test]
    public function it_treats_a_zero_target_as_fully_reached(): void
    {
        $card = $this->card(['current_value' => 0, 'target_value' => 0]);

        $this->assertSame(100.0, $card->percentage());
        $this->assertTrue($card->isReached());
    }

    #[Test]
    public function it_labels_the_progress_with_the_unit_of_the_target(): void
    {
        $card = $this->card(['current_value' => 12, 'target_value' => 28]);

        $this->assertSame('12 / 28 km', $card->progressLabel());
        $this->assertSame('28 km', $card->targetLabel());
    }

    #[Test]
    public function it_labels_a_count_progress_without_a_unit(): void
    {
        $card = $this->card(['current_value' => 3, 'target_value' => 5], ChallengeTemplateKey::SportActivityCount);

        $this->assertSame('3 / 5', $card->progressLabel());
    }

    #[Test]
    public function it_labels_the_typical_week_of_the_player(): void
    {
        $card = $this->card(['baseline_value' => 25, 'target_value' => 28]);

        $this->assertSame('Votre semaine type : 25 km', $card->baselineLabel());
    }

    #[Test]
    public function it_flags_a_proposed_challenge_whose_target_is_already_reached(): void
    {
        $card = $this->card(['current_value' => 30, 'target_value' => 28]);

        $this->assertTrue($card->isAlreadyReached());
    }

    #[Test]
    public function it_does_not_flag_a_proposed_challenge_below_its_target(): void
    {
        $card = $this->card(['current_value' => 12, 'target_value' => 28]);

        $this->assertFalse($card->isAlreadyReached());
    }

    #[Test]
    public function it_does_not_flag_an_accepted_challenge_as_already_reached(): void
    {
        $card = $this->card(['current_value' => 30, 'target_value' => 28], state: 'accepted');

        $this->assertFalse($card->isAlreadyReached());
        $this->assertTrue($card->isReached());
    }

    #[Test]
    public function it_does_not_reach_the_target_when_the_rounded_label_merely_looks_like_it(): void
    {
        $card = $this->card(['current_value' => 27.96, 'target_value' => 28]);

        $this->assertSame('28 / 28 km', $card->progressLabel());
        $this->assertFalse($card->isReached());
        $this->assertFalse($card->isAlreadyReached());
        $this->assertLessThan(100.0, $card->percentage());
        $this->assertSame(99, $card->roundedPercentage());
    }

    #[Test]
    public function it_reaches_the_target_exactly_on_the_target_value(): void
    {
        $card = $this->card(['current_value' => 28, 'target_value' => 28]);

        $this->assertTrue($card->isReached());
        $this->assertSame(100, $card->roundedPercentage());
    }

    #[Test]
    public function it_renders_the_bar_width_from_the_exact_percentage_with_a_dot_decimal_separator(): void
    {
        $card = $this->card(['current_value' => 12, 'target_value' => 28]);

        $this->assertSame('42.86%', $card->barWidth());
    }

    #[Test]
    public function it_names_the_template_and_describes_its_target(): void
    {
        $card = $this->card(['target_value' => 28]);

        $this->assertSame('Distance sportive', $card->name());
        $this->assertSame('Parcourez 28 km cette semaine.', $card->description());
    }

    #[Test]
    public function it_labels_the_status_and_exposes_its_chip_classes(): void
    {
        $card = $this->card([], state: 'accepted');

        $this->assertSame('En cours', $card->statusLabel());
        $this->assertSame(ChallengeStatus::Accepted->chipClass(), $card->chipClass());
    }

    #[Test]
    public function it_builds_the_accessible_labels_of_the_progress_and_of_both_actions(): void
    {
        $card = $this->card();

        $this->assertSame('Progression du défi Distance sportive', $card->progressAccessibleLabel());
        $this->assertSame('Relever le défi Distance sportive', $card->acceptAccessibleLabel());
        $this->assertSame('Passer le défi Distance sportive', $card->declineAccessibleLabel());
    }

    #[Test]
    public function it_labels_the_reward_and_the_age_of_the_progress(): void
    {
        $card = $this->card(['xp_reward' => 50, 'updated_at' => now()->subHours(2)]);

        $this->assertSame('+50 XP', $card->rewardLabel());
        $this->assertSame('Progression mise à jour il y a 2 heures', $card->updatedLabel());
    }

    #[Test]
    public function it_says_just_now_instead_of_zero_seconds_for_a_progress_updated_this_very_second(): void
    {
        $card = $this->card(['updated_at' => now()]);

        $this->assertSame('Progression mise à jour à l\'instant', $card->updatedLabel());
    }

    #[Test]
    public function it_flags_the_reward_as_earned_only_on_a_completed_challenge(): void
    {
        $this->assertTrue($this->card([], state: 'completed')->hasEarnedReward());
        $this->assertFalse($this->card([], state: 'failed')->hasEarnedReward());
        $this->assertFalse($this->card([], state: 'accepted')->hasEarnedReward());
    }

    #[Test]
    public function it_labels_the_card_in_english(): void
    {
        $this->app->setLocale('en');
        $card = $this->card(['current_value' => 12, 'target_value' => 28, 'updated_at' => now()->subHours(2)]);

        $this->assertSame('Take on the challenge: Sport distance', $card->acceptAccessibleLabel());
        $this->assertSame('Skip the challenge: Sport distance', $card->declineAccessibleLabel());
        $this->assertSame('12 / 28 km', $card->progressLabel());
        $this->assertSame('Progress updated 2 hours ago', $card->updatedLabel());
    }

    #[Test]
    public function it_carries_the_respondable_flag_it_was_given(): void
    {
        $challenge = Challenge::factory()->create();

        $this->assertTrue(ChallengeCard::fromChallenge($challenge, true)->isRespondable);
        $this->assertFalse(ChallengeCard::fromChallenge($challenge, false)->isRespondable);
    }

    /**
     * @return array<string, array{string, bool, bool}>
     */
    public static function statusesWithTheirTracking(): array
    {
        return [
            'proposed' => ['proposed', true, false],
            'accepted' => ['accepted', true, false],
            'completed' => ['completed', true, true],
            'failed' => ['failed', true, true],
            'declined' => ['declined', false, true],
            'expired' => ['expired', false, true],
        ];
    }

    #[Test]
    #[DataProvider('statusesWithTheirTracking')]
    public function it_tracks_progress_only_for_challenges_that_were_measured_and_flags_the_closed_ones(string $state, bool $tracksProgress, bool $isClosed): void
    {
        $card = $this->card([], state: $state);

        $this->assertSame($tracksProgress, $card->tracksProgress());
        $this->assertSame($isClosed, $card->isClosed());
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function statusesWithTheirClosureWait(): array
    {
        return [
            'proposed' => ['proposed', false],
            'accepted' => ['accepted', true],
            'completed' => ['completed', false],
            'failed' => ['failed', false],
            'declined' => ['declined', false],
            'expired' => ['expired', false],
        ];
    }

    #[Test]
    #[DataProvider('statusesWithTheirClosureWait')]
    public function it_awaits_the_closure_only_while_an_accepted_challenge_is_unresolved(string $state, bool $isAwaitingClosure): void
    {
        $this->assertSame($isAwaitingClosure, $this->card([], state: $state)->isAwaitingClosure());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function card(array $attributes = [], ChallengeTemplateKey $template = ChallengeTemplateKey::SportDistance, string $state = 'proposed'): ChallengeCard
    {
        $factory = Challenge::factory()->forTemplate($template);
        $challenge = ($state === 'proposed' ? $factory : $factory->{$state}())->create($attributes);

        return ChallengeCard::fromChallenge($challenge->fresh(), true);
    }
}

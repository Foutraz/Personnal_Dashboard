<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Exceptions\InvalidChallengeConfigException;
use Functional\Gamification\Services\Dto\ChallengeSettings;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChallengeSettingsTest extends TestCase
{
    #[Test]
    public function it_reads_the_global_settings_from_the_configuration(): void
    {
        $settings = ChallengeSettings::fromConfig();

        $this->assertSame(4, $settings->historyWeeks);
        $this->assertSame(2, $settings->minActiveWeeks);
        $this->assertSame(0.1, $settings->stretchRatio);
        $this->assertSame(48, $settings->closingGraceHours);
        $this->assertSame(50, $settings->xpReward);
    }

    #[Test]
    public function it_reads_the_configuration_again_on_every_call(): void
    {
        $firstRead = ChallengeSettings::fromConfig();
        config(['gamification.challenges.xp_reward' => $firstRead->xpReward + 1]);

        $this->assertSame($firstRead->xpReward + 1, ChallengeSettings::fromConfig()->xpReward);
    }

    #[Test]
    public function it_follows_a_changed_configuration(): void
    {
        $xpReward = faker()->number(60, 900);
        config(['gamification.challenges.xp_reward' => $xpReward, 'gamification.challenges.stretch_ratio' => 0]);

        $settings = ChallengeSettings::fromConfig();

        $this->assertSame($xpReward, $settings->xpReward);
        $this->assertSame(0.0, $settings->stretchRatio);
    }

    #[Test]
    public function it_accepts_the_largest_reward_the_ledger_can_store(): void
    {
        config(['gamification.challenges.xp_reward' => 65535]);

        $this->assertSame(65535, ChallengeSettings::fromConfig()->xpReward);
    }

    #[Test]
    public function it_refuses_a_reward_the_ledger_cannot_store(): void
    {
        config(['gamification.challenges.xp_reward' => 70000]);

        $this->expectExceptionObject(InvalidChallengeConfigException::integerBetween('gamification.challenges.xp_reward', 70000, 1, 65535));

        ChallengeSettings::fromConfig();
    }

    #[Test]
    public function it_refuses_a_reward_that_is_not_positive(): void
    {
        config(['gamification.challenges.xp_reward' => 0]);

        $this->expectExceptionObject(InvalidChallengeConfigException::integerBetween('gamification.challenges.xp_reward', 0, 1, 65535));

        ChallengeSettings::fromConfig();
    }

    #[Test]
    public function it_refuses_more_active_weeks_than_history_weeks(): void
    {
        config(['gamification.challenges.min_active_weeks' => 5]);

        $this->expectExceptionObject(InvalidChallengeConfigException::integerBetween('gamification.challenges.min_active_weeks', 5, 1, 4));

        ChallengeSettings::fromConfig();
    }

    #[Test]
    public function it_accepts_as_many_active_weeks_as_history_weeks(): void
    {
        config(['gamification.challenges.min_active_weeks' => 4]);

        $this->assertSame(4, ChallengeSettings::fromConfig()->minActiveWeeks);
    }

    #[Test]
    public function it_refuses_an_empty_history(): void
    {
        config(['gamification.challenges.history_weeks' => 0]);

        $this->expectExceptionObject(InvalidChallengeConfigException::integerBetween('gamification.challenges.history_weeks', 0, 1, 52));

        ChallengeSettings::fromConfig();
    }

    #[Test]
    public function it_accepts_the_longest_history_the_queries_can_afford(): void
    {
        config(['gamification.challenges.history_weeks' => 52]);

        $this->assertSame(52, ChallengeSettings::fromConfig()->historyWeeks);
    }

    #[Test]
    public function it_refuses_a_history_longer_than_a_year(): void
    {
        config(['gamification.challenges.history_weeks' => 53]);

        $this->expectExceptionObject(InvalidChallengeConfigException::integerBetween('gamification.challenges.history_weeks', 53, 1, 52));

        ChallengeSettings::fromConfig();
    }

    #[Test]
    public function it_refuses_a_negative_stretch_ratio(): void
    {
        config(['gamification.challenges.stretch_ratio' => -0.1]);

        $this->expectExceptionObject(InvalidChallengeConfigException::numberAtLeast('gamification.challenges.stretch_ratio', -0.1, 0));

        ChallengeSettings::fromConfig();
    }

    #[Test]
    public function it_refuses_a_stretch_ratio_that_is_not_a_number(): void
    {
        config(['gamification.challenges.stretch_ratio' => 'ten percent']);

        $this->expectExceptionObject(InvalidChallengeConfigException::numberAtLeast('gamification.challenges.stretch_ratio', 'ten percent', 0));

        ChallengeSettings::fromConfig();
    }

    #[Test]
    public function it_accepts_an_integer_stretch_ratio(): void
    {
        config(['gamification.challenges.stretch_ratio' => 1]);

        $this->assertSame(1.0, ChallengeSettings::fromConfig()->stretchRatio);
    }

    #[Test]
    public function it_refuses_a_negative_grace(): void
    {
        config(['gamification.challenges.closing_grace_hours' => -1]);

        $this->expectExceptionObject(InvalidChallengeConfigException::integerBetween('gamification.challenges.closing_grace_hours', -1, 0, 168));

        ChallengeSettings::fromConfig();
    }

    #[Test]
    public function it_accepts_a_grace_of_one_week(): void
    {
        config(['gamification.challenges.closing_grace_hours' => 168]);

        $this->assertSame(168, ChallengeSettings::fromConfig()->closingGraceHours);
    }

    #[Test]
    public function it_refuses_a_grace_longer_than_one_week(): void
    {
        config(['gamification.challenges.closing_grace_hours' => 169]);

        $this->expectExceptionObject(InvalidChallengeConfigException::integerBetween('gamification.challenges.closing_grace_hours', 169, 0, 168));

        ChallengeSettings::fromConfig();
    }

    #[Test]
    public function it_accepts_a_closing_without_grace(): void
    {
        config(['gamification.challenges.closing_grace_hours' => 0]);

        $this->assertSame(0, ChallengeSettings::fromConfig()->closingGraceHours);
    }

    #[Test]
    public function it_refuses_a_setting_that_is_absent(): void
    {
        config(['gamification.challenges.history_weeks' => null]);

        $this->expectExceptionObject(InvalidChallengeConfigException::integerBetween('gamification.challenges.history_weeks', null, 1, 52));

        ChallengeSettings::fromConfig();
    }
}

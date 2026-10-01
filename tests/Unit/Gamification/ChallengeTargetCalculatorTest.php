<?php

namespace Tests\Unit\Gamification;

use Functional\Gamification\Challenges\ChallengeTargetCalculator;
use Functional\Gamification\Services\Dto\ChallengeSettings;
use Functional\Gamification\Services\Dto\ChallengeTemplateSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ChallengeTargetCalculatorTest extends TestCase
{
    /**
     * @return array<string, array{0: float, 1: float, 2: float, 3: list<float>, 4: float, 5: float}>
     */
    public static function targets(): array
    {
        return [
            'median stretched and rounded up to the step' => [1, 5, 300, [10, 20, 30, 40], 25.0, 28.0],
            'whole stretched value is not pushed to the next step by float noise' => [1, 5, 300, [10, 10, 10, 10], 10.0, 11.0],
            'floor lifts a small target' => [1, 5, 300, [0, 0, 1, 2], 0.5, 5.0],
            'cap lowers a large target' => [1, 5, 300, [400, 500, 450, 480], 465.0, 300.0],
            'coarse step rounds up to its next multiple' => [50, 100, 10000, [800, 1200, 1000, 0], 900.0, 1000.0],
            'fractional step rounds up to its next multiple' => [0.5, 1, 30, [2, 3, 4, 5], 3.5, 4.0],
            'fractional step keeps an exact multiple' => [0.5, 1, 30, [5, 5, 5, 5], 5.0, 5.5],
            'small counts still grow by one step' => [1, 1, 14, [1, 1, 1, 1], 1.0, 2.0],
            'order of the weeks does not change the median' => [1, 5, 300, [40, 10, 30, 20], 25.0, 28.0],
        ];
    }

    /**
     * @param  list<float>  $weeklyValues
     */
    #[Test]
    #[DataProvider('targets')]
    public function it_derives_the_baseline_and_the_target_from_the_weekly_values(float $step, float $floor, float $cap, array $weeklyValues, float $baseline, float $target): void
    {
        $challengeTarget = $this->calculator()->target(new ChallengeTemplateSettings($step, $floor, $cap), $this->settings(), $weeklyValues);

        $this->assertSame($baseline, $challengeTarget->baseline);
        $this->assertSame($target, $challengeTarget->target);
    }

    #[Test]
    public function it_proposes_no_target_with_a_single_active_week(): void
    {
        $challengeTarget = $this->calculator()->target(new ChallengeTemplateSettings(1, 5, 300), $this->settings(), [0, 0, 0, 12]);

        $this->assertNull($challengeTarget);
    }

    #[Test]
    public function it_proposes_no_target_without_any_history(): void
    {
        $challengeTarget = $this->calculator()->target(new ChallengeTemplateSettings(1, 5, 300), $this->settings(), []);

        $this->assertNull($challengeTarget);
    }

    #[Test]
    public function it_requires_as_many_active_weeks_as_configured(): void
    {
        $settings = new ChallengeSettings(4, 3, 0.1, 48, 50);
        $template = new ChallengeTemplateSettings(1, 5, 300);

        $this->assertNotNull($this->calculator()->target($template, $settings, [0, 10, 20, 30]));
        $this->assertNull($this->calculator()->target($template, $settings, [0, 0, 20, 30]));
    }

    #[Test]
    public function it_keeps_the_baseline_as_the_target_without_stretch(): void
    {
        $settings = new ChallengeSettings(4, 2, 0.0, 48, 50);

        $challengeTarget = $this->calculator()->target(new ChallengeTemplateSettings(1, 5, 300), $settings, [10, 20, 30, 40]);

        $this->assertSame(25.0, $challengeTarget->baseline);
        $this->assertSame(25.0, $challengeTarget->target);
    }

    #[Test]
    public function it_rounds_the_baseline_to_two_decimals(): void
    {
        $challengeTarget = $this->calculator()->target(new ChallengeTemplateSettings(1, 1, 300), $this->settings(), [1.0, 1.0, 1.013, 2.0]);

        $this->assertSame(1.01, $challengeTarget->baseline);
    }

    #[Test]
    public function it_takes_the_middle_value_of_an_odd_history(): void
    {
        $settings = new ChallengeSettings(5, 2, 0.1, 48, 50);

        $challengeTarget = $this->calculator()->target(new ChallengeTemplateSettings(1, 1, 300), $settings, [10, 50, 20, 30, 40]);

        $this->assertSame(30.0, $challengeTarget->baseline);
        $this->assertSame(33.0, $challengeTarget->target);
    }

    private function calculator(): ChallengeTargetCalculator
    {
        return new ChallengeTargetCalculator;
    }

    private function settings(): ChallengeSettings
    {
        return new ChallengeSettings(4, 2, 0.1, 48, 50);
    }
}

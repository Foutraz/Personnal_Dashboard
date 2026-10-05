<?php

namespace Tests\Feature\Gamification;

use Functional\Gamification\Exceptions\InvalidFinanceXpConfigException;
use Functional\Gamification\Services\Dto\FinanceXpSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FinanceXpSettingsTest extends TestCase
{
    #[Test]
    public function it_reads_the_settings_from_the_configuration(): void
    {
        $settings = FinanceXpSettings::fromConfig();

        $this->assertSame(20, $settings->positiveSavingsMonth);
        $this->assertSame(10, $settings->investmentContributionMonth);
        $this->assertSame(10.0, $settings->investmentMinimumNetBought);
    }

    #[Test]
    public function it_follows_a_changed_configuration(): void
    {
        config(['gamification.xp.finance.investment_minimum_net_bought' => 25.5]);

        $this->assertSame(25.5, FinanceXpSettings::fromConfig()->investmentMinimumNetBought);
    }

    #[Test]
    public function it_accepts_an_integer_minimum(): void
    {
        config(['gamification.xp.finance.investment_minimum_net_bought' => 50]);

        $this->assertSame(50.0, FinanceXpSettings::fromConfig()->investmentMinimumNetBought);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function unusableMinimums(): array
    {
        return [
            'zero' => [0],
            'negative' => [-5],
            'tiny negative float' => [-0.01],
            'zero float' => [0.0],
            'numeric string' => ['10'],
            'text' => ['ten euros'],
            'absent' => [null],
            'boolean' => [true],
            'array' => [[10]],
            'infinity' => [INF],
            'not a number' => [NAN],
        ];
    }

    #[Test]
    #[DataProvider('unusableMinimums')]
    public function it_refuses_a_minimum_that_is_not_a_finite_amount_above_zero(mixed $configured): void
    {
        config(['gamification.xp.finance.investment_minimum_net_bought' => $configured]);

        $this->expectException(InvalidFinanceXpConfigException::class);

        FinanceXpSettings::fromConfig();
    }

    #[Test]
    public function it_names_the_offending_setting(): void
    {
        config(['gamification.xp.finance.investment_minimum_net_bought' => 0]);

        $this->expectExceptionObject(InvalidFinanceXpConfigException::numberAboveZero('gamification.xp.finance.investment_minimum_net_bought', 0));

        FinanceXpSettings::fromConfig();
    }

    #[Test]
    public function it_refuses_a_negative_points_setting(): void
    {
        config(['gamification.xp.finance.positive_savings_month' => -1]);

        $this->expectExceptionObject(InvalidFinanceXpConfigException::integerAtLeastZero('gamification.xp.finance.positive_savings_month', -1));

        FinanceXpSettings::fromConfig();
    }

    #[Test]
    public function it_refuses_a_points_setting_that_is_not_an_integer(): void
    {
        config(['gamification.xp.finance.investment_contribution_month' => '10']);

        $this->expectExceptionObject(InvalidFinanceXpConfigException::integerAtLeastZero('gamification.xp.finance.investment_contribution_month', '10'));

        FinanceXpSettings::fromConfig();
    }
}
